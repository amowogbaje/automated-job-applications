<?php

namespace App\Console\Commands;

use App\Models\ApplicationDraft;
use App\Models\JobListing;
use App\Models\Resume;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;

class GenerateApplicationDrafts extends Command
{
    protected $signature = 'applications:generate
        {--limit=10 : Max number of drafts to generate this run}
        {--min-score=2 : Only draft for jobs at or above this match_score}';

    protected $description = 'Generate tailored cover-letter drafts (+ a tailored resume PDF) for your best-matching, undrafted jobs — review before sending, this does not submit anything';

    public function handle(CoverLetterWriter $writer, ResumeTailor $tailor, ResumeCompiler $compiler): int
    {
        $resume = Resume::current();

        if (! $resume) {
            $this->error('No active resume found. Run `php artisan resume:import --pdf=... --website=...` first.');
            return self::FAILURE;
        }

        $jobs = JobListing::query()
            ->notDismissed()
            ->last24Hours()
            ->where('match_score', '>=', (int) $this->option('min-score'))
            ->whereDoesntHave('applicationDraft')
            ->orderByDesc('match_score')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No new jobs to draft for right now.');
            return self::SUCCESS;
        }

        $generated = 0;

        foreach ($jobs as $job) {
            $this->info("Drafting for: {$job->title} @ {$job->company}");

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
                $resumePdfPath = $compiler->compile($resume, $snapshot, "job-{$job->id}");
            } catch (\Throwable $e) {
                $this->warn("  Resume PDF compile failed ({$e->getMessage()}) — draft saved without an attached PDF.");
            }

            ApplicationDraft::updateOrCreate(
                ['job_listing_id' => $job->id],
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

        $this->info("Generated {$generated} draft(s). Review and send them yourself from the dashboard — nothing is submitted automatically.");

        return self::SUCCESS;
    }
}
