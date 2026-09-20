<?php

namespace App\Console\Commands;

use App\Mail\JobApplicationMail;
use App\Mail\JobDigestMail;
use App\Models\ApplicantProfile;
use App\Models\ApplicationDraft;
use App\Models\JobListing;
use App\Services\AI\AnthropicClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessApplications extends Command
{
    protected $signature = 'applications:process {--min-score=2}';

    protected $description = 'Auto-send applications for email-apply jobs; bundle web/form-apply jobs into a digest email';

    public function handle(AnthropicClient $ai): int
    {
        $profile = ApplicantProfile::current();

        if (empty($profile->skills) && empty($profile->summary)) {
            $this->error('No applicant profile found. Run `php artisan resume:import path/to/resume.txt` first.');
            return self::FAILURE;
        }

        $minScore = (int) $this->option('min-score');
        $autoSend = (bool) config('services.job_aggregator.auto_send_applications', false);
        $resumePath = config('services.job_aggregator.resume_pdf_path');
        $digestTo = config('services.job_aggregator.digest_email');

        if (! $digestTo) {
            $this->error('MAIL_DIGEST_TO is not set in .env — this is where digests and applications get sent from/to. Set it and retry.');
            return self::FAILURE;
        }

        $this->processEmailApplications($ai, $profile, $minScore, $autoSend, $resumePath);
        $this->sendDigest($minScore, $digestTo);

        return self::SUCCESS;
    }

    protected function processEmailApplications(AnthropicClient $ai, ApplicantProfile $profile, int $minScore, bool $autoSend, ?string $resumePath): void
    {
        $jobs = JobListing::query()
            ->where('apply_method', 'email')
            ->where('is_applied', false)
            ->where('is_dismissed', false)
            ->where('match_score', '>=', $minScore)
            ->last24Hours()
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No new email-apply jobs to process.');
            return;
        }

        foreach ($jobs as $job) {
            $this->info(($autoSend ? 'Sending' : '[DRY RUN] Would send') . " application: {$job->title} @ {$job->company} -> {$job->apply_email}");

            $result = $ai->completeJson(
                systemPrompt: 'You write honest, specific application emails. Only reference skills, experience, and '
                    . 'projects explicitly given to you — never fabricate technologies, employers, or achievements. '
                    . 'This text will be sent directly as an email body, so write it as a complete, ready-to-send '
                    . 'email including a greeting and sign-off, not a summary. Keep it under 200 words. '
                    . 'Return JSON with exactly one key: "email_body".',
                userPrompt: json_encode([
                    'job_title' => $job->title,
                    'company' => $job->company,
                    'job_description' => \Illuminate\Support\Str::limit($job->description, 3000),
                    'candidate_summary' => $profile->summary,
                    'candidate_skills' => $profile->skills,
                    'candidate_projects' => $profile->projects,
                ]),
                maxTokens: 800,
            );

            if (! $result || empty($result['email_body'])) {
                $this->warn("  Skipped (AI call failed) — {$job->url}");
                continue;
            }

            $draft = ApplicationDraft::updateOrCreate(
                ['job_listing_id' => $job->id],
                [
                    'cover_letter' => $result['email_body'],
                    'status' => $autoSend ? 'sent' : 'ready', // 'ready' = generated but not sent, review at /drafts
                ]
            );

            if (! $autoSend) {
                continue; // dry run: draft is queued for your manual review/send instead
            }

            try {
                Mail::to($job->apply_email)->send(
                    new JobApplicationMail($job, $result['email_body'], $resumePath)
                );

                $job->update(['is_applied' => true, 'applied_at' => now()]);
            } catch (\Throwable $e) {
                $this->error("  Send failed for {$job->apply_email}: {$e->getMessage()}");
                $draft->update(['status' => 'ready']); // fall back to manual review
            }
        }
    }

    protected function sendDigest(int $minScore, string $digestTo): void
    {
        $jobs = JobListing::query()
            ->where('apply_method', 'web')
            ->where('is_dismissed', false)
            ->where('match_score', '>=', $minScore)
            ->whereNull('notified_at')
            ->last24Hours()
            ->orderByDesc('match_score')
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No new web-apply jobs for the digest this run.');
            return;
        }

        Mail::to($digestTo)->send(new JobDigestMail($jobs));

        JobListing::whereIn('id', $jobs->pluck('id'))->update(['notified_at' => now()]);

        $this->info("Sent digest with {$jobs->count()} job(s) to {$digestTo}.");
    }
}
