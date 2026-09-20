<?php

namespace App\Console\Commands;

use App\Mail\JobApplicationMail;
use App\Mail\JobDigestMail;
use App\Models\ApplicationDraft;
use App\Models\JobListing;
use App\Models\Resume;
use App\Services\AI\AiClientInterface;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessApplications extends Command
{
    protected $signature = 'applications:process {--min-score=2}';

    protected $description = 'Auto-send applications for email-apply jobs (with a tailored resume PDF attached); bundle web/form-apply jobs into a digest email';

    public function handle(AiClientInterface $ai, ResumeTailor $tailor, ResumeCompiler $compiler): int
    {
        $resume = Resume::current();

        if (! $resume) {
            $this->error('No active resume found. Run `php artisan resume:import --pdf=... --website=...` first.');
            return self::FAILURE;
        }

        $minScore = (int) $this->option('min-score');
        $autoSend = (bool) config('services.job_aggregator.auto_send_applications', false);
        $fallbackResumePath = config('services.job_aggregator.resume_pdf_path');
        $digestTo = config('services.job_aggregator.digest_email');

        if (! $digestTo) {
            $this->error('MAIL_DIGEST_TO is not set in .env — this is where digests and applications get sent from/to. Set it and retry.');
            return self::FAILURE;
        }

        $this->processEmailApplications($ai, $tailor, $compiler, $resume, $minScore, $autoSend, $fallbackResumePath);
        $this->sendDigest($minScore, $digestTo);

        return self::SUCCESS;
    }

    protected function processEmailApplications(
        AiClientInterface $ai,
        ResumeTailor $tailor,
        ResumeCompiler $compiler,
        Resume $resume,
        int $minScore,
        bool $autoSend,
        ?string $fallbackResumePath,
    ): void {
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
                    'job_description' => \Illuminate\Support\Str::limit($job->description, 3000),
                    'candidate_summary' => $resume->summary,
                    'candidate_skills' => $snapshot['lead_skills'] ?? $resume->skills->pluck('name'),
                    'candidate_projects' => $resume->projects->whereIn('name', $snapshot['lead_projects'] ?? [])->values(),
                ]),
                maxTokens: 800,
            );

            if (! $result || empty($result['email_body'])) {
                $this->warn("  Skipped (AI call failed) — {$job->url}");
                continue;
            }

            // Compile a fresh, tailored PDF straight from the resumes tables for this job.
            // Falls back to the static RESUME_PDF_PATH only if compilation itself fails.
            $resumePdfPath = $fallbackResumePath;
            try {
                $resumePdfPath = $compiler->compile($resume, $snapshot, "job-{$job->id}");
            } catch (\Throwable $e) {
                $this->warn("  Resume PDF compile failed ({$e->getMessage()}) — falling back to RESUME_PDF_PATH.");
            }

            $draft = ApplicationDraft::updateOrCreate(
                ['job_listing_id' => $job->id],
                [
                    'cover_letter' => $result['email_body'],
                    'resume_snapshot' => $snapshot,
                    'resume_pdf_path' => $resumePdfPath,
                    'status' => $autoSend ? 'sent' : 'ready', // 'ready' = generated but not sent, review at /drafts
                ]
            );

            if (! $autoSend) {
                continue; // dry run: draft is queued for your manual review/send instead
            }

            try {
                Mail::to($job->apply_email)->send(
                    new JobApplicationMail($job, $result['email_body'], $resumePdfPath)
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
