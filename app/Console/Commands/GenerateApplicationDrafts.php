<?php

namespace App\Console\Commands;

use App\Models\ApplicationDraft;
use App\Models\JobListing;
use App\Models\Resume;
use App\Services\AI\AiClientInterface;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateApplicationDrafts extends Command
{
    protected $signature = 'applications:generate
        {--limit=10 : Max number of drafts to generate this run}
        {--min-score=2 : Only draft for jobs at or above this match_score}';

    protected $description = 'Generate tailored cover-letter drafts (+ a tailored resume PDF) for your best-matching, undrafted jobs — review before sending, this does not submit anything';

    public function handle(AiClientInterface $ai, ResumeTailor $tailor, ResumeCompiler $compiler): int
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
            $result = $ai->completeJson(
                systemPrompt: 'You write honest, specific cover letters. Only reference skills, experience, and '
                    . 'projects that are explicitly given to you — never fabricate technologies, employers, or '
                    . 'achievements. Keep the tone direct and human, not generic AI filler. '
                    . 'Return JSON with exactly two keys: "cover_letter" (string, ~150-200 words) and '
                    . '"tailored_summary" (string, 1-2 sentences on which of the candidate\'s skills/projects to '
                    . 'lead with for this specific role, and any gaps to be upfront about).',
                userPrompt: json_encode([
                    'job_title' => $job->title,
                    'company' => $job->company,
                    'job_description' => Str::limit($job->description, 3000),
                    'candidate_summary' => $resume->summary,
                    'candidate_skills' => $snapshot['lead_skills'] ?? $resume->skills->pluck('name'),
                    'candidate_projects' => $resume->projects->whereIn('name', $snapshot['lead_projects'] ?? [])->values(),
                    'gap_notes' => $snapshot['gap_notes'] ?? null,
                ]),
                maxTokens: 1024,
            );

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

            $generated++;
        }

        $this->info("Generated {$generated} draft(s). Review and send them yourself from the dashboard — nothing is submitted automatically.");

        return self::SUCCESS;
    }
}
