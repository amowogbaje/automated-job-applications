<?php

namespace App\Console\Commands;

use App\Mail\JobApplicationMail;
use App\Mail\JobDigestMail;
use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\JobListingUserState;
use App\Models\Resume;
use App\Models\User;
use App\Services\AI\AiClientInterface;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProcessApplications extends Command
{
    protected $signature = 'applications:process {--min-score=2}';

    protected $description = 'Per account with an active resume: auto-send applications for email-apply jobs (tailored resume attached, if auto-send is enabled in that account\'s profile), and email a digest of web/form-apply jobs — nothing here is one shared, install-wide run anymore';

    public function handle(AiClientInterface $ai, ResumeTailor $tailor, ResumeCompiler $compiler): int
    {
        $minScore = (int) $this->option('min-score');
        $fallbackResumePath = config('services.job_aggregator.resume_pdf_path');

        $users = User::whereHas('resumes', fn ($q) => $q->where('is_active', true))->get();

        if ($users->isEmpty()) {
            $this->error('No accounts with an active resume found — nothing to process. Import/claim a resume first.');
            return self::FAILURE;
        }

        foreach ($users as $user) {
            $resume = Resume::current($user->id);
            if (! $resume) {
                continue; // shouldn't happen given the query above, but stay safe
            }

            $profile = CareerProfile::forUser($user->id);
            $this->info("== {$user->email} ==");

            $this->processEmailApplications($ai, $tailor, $compiler, $user, $resume, $profile, $minScore, $fallbackResumePath);
            $this->sendDigest($user, $profile, $minScore);
        }

        return self::SUCCESS;
    }

    protected function processEmailApplications(
        AiClientInterface $ai,
        ResumeTailor $tailor,
        ResumeCompiler $compiler,
        User $user,
        Resume $resume,
        CareerProfile $profile,
        int $minScore,
        ?string $fallbackResumePath,
    ): void {
        $candidates = JobListing::query()
            ->where('apply_method', 'email')
            ->notAppliedBy($user->id)
            ->notDismissedBy($user->id)
            ->last24Hours()
            ->get();

        $jobs = $this->scoreAndFilter($candidates, $profile, $minScore);

        if ($jobs->isEmpty()) {
            $this->info('  No new email-apply jobs to process.');
            return;
        }

        foreach ($jobs as $job) {
            $this->info('  ' . ($profile->auto_send_enabled ? 'Sending' : '[not auto-sending, drafting only]') . " application: {$job->title} @ {$job->company} -> {$job->apply_email}");

            $snapshot = $tailor->tailorFor($resume, $job);

            $result = $ai->completeJson(
                systemPrompt: 'You write honest, specific application emails. Only reference skills, experience, and '
                    . 'projects explicitly given to you — never fabricate technologies, employers, or achievements. '
                    . 'This text will be sent directly as an email body, so write it as a complete, ready-to-send '
                    . 'email including a greeting and sign-off, not a summary. Keep it under 200 words. '
                    . 'Return JSON with exactly one key: "email_body".',
                userPrompt: json_encode([
                    'job_title' => $job->title,
                    'company' => $job->company,
                    'job_description' => Str::limit($job->description, 3000),
                    'candidate_summary' => $resume->summary,
                    'candidate_skills' => $snapshot['lead_skills'] ?? $resume->skills->pluck('name'),
                    'candidate_projects' => $resume->projects->whereIn('name', $snapshot['lead_projects'] ?? [])->values(),
                ]),
                maxTokens: 800,
            );

            if (! $result || empty($result['email_body'])) {
                $this->warn("    Skipped (AI call failed) — {$job->url}");
                continue;
            }

            // Compile a fresh, tailored PDF straight from the resumes tables for this job.
            // Falls back to the static RESUME_PDF_PATH only if compilation itself fails.
            $resumePdfPath = $fallbackResumePath;
            try {
                $resumePdfPath = $compiler->compile($resume, $snapshot, "job-{$job->id}-user-{$user->id}");
            } catch (\Throwable $e) {
                $this->warn("    Resume PDF compile failed ({$e->getMessage()}) — falling back to RESUME_PDF_PATH.");
            }

            $draft = ApplicationDraft::updateOrCreate(
                ['job_listing_id' => $job->id, 'user_id' => $user->id],
                [
                    'cover_letter' => $result['email_body'],
                    'resume_snapshot' => $snapshot,
                    'resume_pdf_path' => $resumePdfPath,
                    'status' => $profile->auto_send_enabled ? 'sent' : 'ready', // 'ready' = generated but not sent, review at /drafts
                ]
            );

            if (! $profile->auto_send_enabled) {
                continue; // queued for manual review/send at /drafts instead
            }

            $to = $profile->notificationEmail();

            try {
                Mail::to($job->apply_email)->send(
                    new JobApplicationMail($job, $result['email_body'], $resumePdfPath)
                );

                JobListingUserState::updateOrCreate(
                    ['job_listing_id' => $job->id, 'user_id' => $user->id],
                    ['is_applied' => true, 'applied_at' => now()]
                );
            } catch (\Throwable $e) {
                $this->error("    Send failed for {$job->apply_email}: {$e->getMessage()}");
                $draft->update(['status' => 'ready']); // fall back to manual review
            }
        }
    }

    protected function sendDigest(User $user, CareerProfile $profile, int $minScore): void
    {
        if (! $profile->digest_enabled) {
            $this->info('  Digest disabled for this account, skipping.');
            return;
        }

        $to = $profile->notificationEmail();
        if (! $to) {
            $this->warn('  No notification email resolvable for this account, skipping digest.');
            return;
        }

        $candidates = JobListing::query()
            ->where('apply_method', 'web')
            ->notDismissedBy($user->id)
            ->notYetNotifiedFor($user->id)
            ->last24Hours()
            ->get();

        $jobs = $this->scoreAndFilter($candidates, $profile, $minScore)
            ->sortByDesc('match_score')
            ->values();

        if ($jobs->isEmpty()) {
            $this->info('  No new web-apply jobs for the digest this run.');
            return;
        }

        Mail::to($to)->send(new JobDigestMail($jobs));

        foreach ($jobs as $job) {
            JobListingUserState::updateOrCreate(
                ['job_listing_id' => $job->id, 'user_id' => $user->id],
                ['notified_at' => now()]
            );
        }

        $this->info("  Sent digest with {$jobs->count()} job(s) to {$to}.");
    }

    // Scores a candidate set against one account's career profile, in
    // memory — required now that relevance isn't a stored column anyone
    // can filter by in SQL, since it's different per account. match_score/
    // matched_keywords are set on each in-memory instance only (never
    // saved) so JobApplicationMail/JobDigestMail's views can still read
    // them the same way they always did.
    protected function scoreAndFilter($candidates, CareerProfile $profile, int $minScore)
    {
        return $candidates
            ->map(function (JobListing $job) use ($profile) {
                $result = $profile->score($job->title, $job->description);
                $job->match_score = $result['score'];
                $job->matched_keywords = $result['matched'];
                $job->personalized_match = $result['matches'];

                return $job;
            })
            ->filter(fn (JobListing $job) => $job->personalized_match && $job->match_score >= $minScore)
            ->values();
    }
}
