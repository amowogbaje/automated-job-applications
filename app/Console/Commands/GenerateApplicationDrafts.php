<?php

namespace App\Console\Commands;

use App\Models\ApplicantProfile;
use App\Models\ApplicationDraft;
use App\Models\JobListing;
use App\Services\AI\AnthropicClient;
use Illuminate\Console\Command;

class GenerateApplicationDrafts extends Command
{
    protected $signature = 'applications:generate
        {--limit=10 : Max number of drafts to generate this run}
        {--min-score=2 : Only draft for jobs at or above this match_score}';

    protected $description = 'Generate tailored cover-letter drafts for your best-matching, undrafted jobs (review before sending — this does not submit anything)';

    public function handle(AnthropicClient $ai): int
    {
        $profile = ApplicantProfile::current();

        if (empty($profile->skills) && empty($profile->summary)) {
            $this->error('No applicant profile found. Run `php artisan resume:import path/to/resume.txt` first.');
            return self::FAILURE;
        }

        $jobs = JobListing::query()
            ->notDismissed()
            ->last24Hours()
            ->where('match_score', '>=', (int) $this->option('min-score'))
            ->whereDoesntHave('applicationDraft') // add relation below, or filter manually if you skip it
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
                    'job_description' => \Illuminate\Support\Str::limit($job->description, 3000),
                    'candidate_summary' => $profile->summary,
                    'candidate_skills' => $profile->skills,
                    'candidate_projects' => $profile->projects,
                ]),
                maxTokens: 1024,
            );

            if (! $result) {
                $this->warn("  Skipped (AI call failed) — {$job->url}");
                continue;
            }

            ApplicationDraft::updateOrCreate(
                ['job_listing_id' => $job->id],
                [
                    'cover_letter' => $result['cover_letter'] ?? null,
                    'tailored_summary' => $result['tailored_summary'] ?? null,
                    'status' => 'draft',
                ]
            );

            $generated++;
        }

        $this->info("Generated {$generated} draft(s). Review and send them yourself from the dashboard — nothing is submitted automatically.");

        return self::SUCCESS;
    }
}
