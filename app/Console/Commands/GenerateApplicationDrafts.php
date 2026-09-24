<?php

namespace App\Console\Commands;

use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\Resume;
use App\Models\User;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;

class GenerateApplicationDrafts extends Command
{
    protected $signature = 'applications:generate
        {--user= : User ID or email to generate for; defaults to the only/first account with an active resume}
        {--limit=10 : Max number of drafts to generate this run}
        {--min-score=2 : Only draft for jobs scoring at or above this against that user\'s career profile}';

    protected $description = 'Generate tailored cover-letter drafts (+ a tailored resume PDF) for one account\'s best-matching, undrafted jobs — review before sending, this does not submit anything';

    public function handle(CoverLetterWriter $writer, ResumeTailor $tailor, ResumeCompiler $compiler): int
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->error('No matching account found. Pass --user=email or an ID, or make sure at least one account has an active resume.');
            return self::FAILURE;
        }

        $resume = Resume::current($user->id);

        if (! $resume) {
            $this->error("No active resume for {$user->email}. Run `php artisan resume:import --pdf=... --user={$user->email}` first.");
            return self::FAILURE;
        }

        $profile = CareerProfile::forUser($user->id);
        $minScore = (int) $this->option('min-score');

        $candidates = JobListing::query()
            ->notDismissedBy($user->id)
            ->last24Hours()
            ->whereDoesntHave('applicationDrafts', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $jobs = $candidates
            ->map(function (JobListing $job) use ($profile) {
                $result = $profile->score($job->title, $job->description);
                $job->match_score = $result['score'];
                $job->matched_keywords = $result['matched'];
                $job->personalized_match = $result['matches'];

                return $job;
            })
            ->filter(fn (JobListing $job) => $job->personalized_match && $job->match_score >= $minScore)
            ->sortByDesc('match_score')
            ->take((int) $this->option('limit'))
            ->values();

        if ($jobs->isEmpty()) {
            $this->info("No new jobs to draft for {$user->email} right now.");
            return self::SUCCESS;
        }

        $generated = 0;

        foreach ($jobs as $job) {
            $this->info("Drafting for {$user->email}: {$job->title} @ {$job->company}");

            // 1) Decide which of the candidate's REAL skills/projects to lead with for this job.
            $snapshot = $tailor->tailorFor($resume, $job);

            // 2) Write the cover letter using that tailored emphasis.
            $result = $writer->write($resume, $job, $snapshot);

            if (! $result) {
                $this->warn("  Skipped (AI call failed) — {$job->url}");
                continue;
            }

            // 3) Compile a tailored resume PDF to go with this specific draft.
            $resumePdfPath = null;
            try {
                $resumePdfPath = $compiler->compile($resume, $snapshot, "job-{$job->id}-user-{$user->id}");
            } catch (\Throwable $e) {
                $this->warn("  Resume PDF compile failed ({$e->getMessage()}) — draft saved without an attached PDF.");
            }

            ApplicationDraft::updateOrCreate(
                ['job_listing_id' => $job->id, 'user_id' => $user->id],
                [
                    'cover_letter' => $result['cover_letter'] ?? null,
                    'tailored_summary' => $result['tailored_summary'] ?? null,
                    'resume_snapshot' => $snapshot,
                    'resume_pdf_path' => $resumePdfPath,
                    'status' => 'draft',
                ]
            );

            // Also cache onto the job row itself, so /jobs shows this letter
            // already generated instead of re-running the AI call if you
            // open the job listing directly instead of going via /drafts.
            // Note: this cache is shared per job, not per user — if a
            // second account generates for the same job with a different
            // resume, theirs overwrites this cache (self-healing on next
            // view via the tailored_for_resume_id check, just not free).
            $job->update([
                'tailored_cover_letter' => $result['cover_letter'] ?? null,
                'tailored_cover_letter_generated_at' => now(),
                'tailored_resume_path' => $resumePdfPath,
                'tailored_resume_snapshot' => $snapshot,
                'tailored_for_resume_id' => $resume->id,
                'tailored_resume_generated_at' => $resumePdfPath ? now() : $job->tailored_resume_generated_at,
            ]);

            $generated++;
        }

        $this->info("Generated {$generated} draft(s) for {$user->email}. Review and send them yourself from /drafts — nothing is submitted automatically.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $option = $this->option('user');

        if ($option) {
            return is_numeric($option) ? User::find($option) : User::where('email', $option)->first();
        }

        $userId = auth()->id() ?? User::whereHas('resumes', fn ($q) => $q->where('is_active', true))->value('id');

        return $userId ? User::find($userId) : null;
    }
}
