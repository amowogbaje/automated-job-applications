<?php

namespace App\Console\Commands;

use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\JobListingUserState;
use App\Models\Resume;
use App\Models\User;
use App\Services\Apply\ApplyResult;
use App\Services\Apply\ApplyRouter;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;

class AutoApply extends Command
{
    protected $signature = 'applications:auto-apply {--min-score=2} {--dry-run : Prepare drafts only, never submit}';

    protected $description = 'Per account: for jobs on a supported ATS channel (employer-keyed Greenhouse/Lever API, or the third-party apply service), prepare a tailored resume + cover letter and — only if the account is in "auto" mode and AUTO_APPLY_LIVE=true — submit within the daily cap. Email-apply jobs stay with applications:process.';

    public function handle(ApplyRouter $router, ResumeTailor $tailor, ResumeCompiler $compiler, CoverLetterWriter $letters): int
    {
        $live = config('ats.auto_apply.live') && ! $this->option('dry-run');
        $globalBudget = (int) config('ats.auto_apply.max_per_run', 10);
        $maxAge = (int) config('ats.auto_apply.max_age_hours', 72);

        $this->info($live ? 'LIVE mode — accounts in "auto" will really submit.' : 'DRY RUN — drafts only, nothing is submitted (set AUTO_APPLY_LIVE=true to go live).');

        $users = User::whereHas('resumes', fn ($q) => $q->where('is_active', true))->get();

        foreach ($users as $user) {
            if ($globalBudget <= 0) {
                $this->warn('Global per-run limit reached.');
                break;
            }

            $profile = CareerProfile::forUser($user->id);
            if ($profile->auto_apply_mode === 'off') {
                continue;
            }

            $resume = Resume::current($user->id);
            if (! $resume) {
                continue;
            }

            $this->info("== {$user->email} (mode: {$profile->auto_apply_mode}) ==");

            // Daily cap counts everything handed to a channel today, including in-flight ones.
            $usedToday = ApplicationDraft::where('user_id', $user->id)
                ->whereIn('status', ['sent', 'pending', 'needs_input'])
                ->whereNotNull('apply_channel')
                ->where('updated_at', '>=', now()->startOfDay())
                ->count();
            $allowance = max(0, (int) $profile->daily_apply_cap - $usedToday);

            $candidates = JobListing::query()
                ->board() // never auto-apply to a social post's tweet URL
                ->whereIn('apply_method', ['web', 'native']) // email-apply jobs belong to applications:process
                ->where('posted_at', '>=', now()->subHours($maxAge))
                ->notAppliedBy($user->id)
                ->notDismissedBy($user->id)
                ->whereDoesntHave('applicationDrafts', fn ($q) => $q->where('user_id', $user->id)) // one attempt per job, ever
                ->get()
                ->map(function (JobListing $job) use ($profile) {
                    $r = $profile->score($job->scoringTitle(), $job->scoringDescription());
                    $job->match_score = $r['score'];
                    $job->personalized_match = $r['matches'];

                    return $job;
                })
                ->filter(fn ($j) => $j->personalized_match && $j->match_score >= (int) $this->option('min-score'))
                ->sortByDesc('match_score')
                ->values();

            $prepared = 0;

            foreach ($candidates as $job) {
                if ($globalBudget <= 0 || ($profile->auto_apply_mode === 'auto' && $live && $allowance <= 0)) {
                    break;
                }

                $driver = $router->driverFor($job);
                if (! $driver) {
                    continue; // no channel for this one -> stays in the digest
                }

                $snapshot = $tailor->tailorFor($resume, $job);
                $letter = $snapshot ? $letters->write($resume, $job, $snapshot) : null;
                if (! $snapshot || ! $letter) {
                    $this->warn("  Skipped (AI step failed): {$job->title} @ {$job->company}");
                    continue;
                }

                try {
                    $pdf = $compiler->compile($resume, $snapshot, "job-{$job->id}-user-{$user->id}");
                } catch (\Throwable $e) {
                    $this->warn("  Skipped (PDF compile failed): {$e->getMessage()}");
                    continue;
                }

                $draft = ApplicationDraft::create([
                    'job_listing_id' => $job->id,
                    'user_id' => $user->id,
                    'cover_letter' => $letter['cover_letter'],
                    'tailored_summary' => $letter['tailored_summary'],
                    'resume_snapshot' => $snapshot,
                    'resume_pdf_path' => $pdf,
                    'status' => 'ready',
                    'apply_channel' => $driver->channel(),
                ]);
                $prepared++;

                if ($profile->auto_apply_mode !== 'auto' || ! $live) {
                    $this->line("  Drafted via {$driver->channel()}: {$job->title} @ {$job->company}");
                    continue;
                }

                $globalBudget--;
                $allowance--;
                $result = $driver->submit($job, $draft, $resume, $profile, $pdf);
                $this->record($draft, $job, $user, $result);
                $this->line("  {$result->status} via {$driver->channel()}: {$job->title} @ {$job->company}" . ($result->error ? " — {$result->error}" : ''));
            }

            $this->info("  Prepared {$prepared} draft(s).");
        }

        return self::SUCCESS;
    }

    private function record(ApplicationDraft $draft, JobListing $job, User $user, ApplyResult $result): void
    {
        match ($result->status) {
            ApplyResult::SUBMITTED => $this->markApplied($draft, $job, $user, $result),
            ApplyResult::PENDING => $draft->update(['status' => 'pending', 'external_ref' => $result->externalRef]),
            ApplyResult::NEEDS_INPUT => $draft->update(['status' => 'needs_input', 'pending_questions' => $result->questions]),
            // Failed -> back to manual review with the reason; never retried automatically.
            default => $draft->update(['status' => 'ready', 'error' => $result->error]),
        };
    }

    private function markApplied(ApplicationDraft $draft, JobListing $job, User $user, ApplyResult $result): void
    {
        $draft->update([
            'status' => 'sent',
            'external_ref' => $result->externalRef,
            'receipt_url' => $result->receiptUrl,
            'submitted_at' => now(),
        ]);

        JobListingUserState::updateOrCreate(
            ['job_listing_id' => $job->id, 'user_id' => $user->id],
            ['is_applied' => true, 'applied_at' => now()]
        );
    }
}
