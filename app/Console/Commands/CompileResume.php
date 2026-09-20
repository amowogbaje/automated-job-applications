<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use App\Models\Resume;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Console\Command;

class CompileResume extends Command
{
    protected $signature = 'resume:compile
        {--job= : Job listing ID to tailor the resume for (reorders real skills/projects, never invents new ones)}
        {--out= : Optional explicit output path; defaults to storage/app/resumes/}';

    protected $description = 'Compile your active database resume into a PDF, optionally tailored for one job';

    public function handle(ResumeCompiler $compiler, ResumeTailor $tailor): int
    {
        $resume = Resume::current();

        if (! $resume) {
            $this->error('No active resume found. Run `php artisan resume:import --pdf=... --website=...` first.');
            return self::FAILURE;
        }

        $snapshot = null;

        if ($jobId = $this->option('job')) {
            $job = JobListing::find($jobId);

            if (! $job) {
                $this->error("Job listing #{$jobId} not found.");
                return self::FAILURE;
            }

            $this->info("Tailoring for: {$job->title} @ {$job->company}...");
            $snapshot = $tailor->tailorFor($resume, $job);

            if (! $snapshot) {
                $this->warn('AI tailoring failed — compiling the untailored resume instead.');
            } else {
                $this->line('  Headline: ' . $snapshot['tailored_headline']);
                $this->line('  Leading with: ' . implode(', ', $snapshot['lead_skills']));
            }
        }

        $path = $compiler->compile($resume, $snapshot, $jobId ?? null ? "job-{$jobId}" : null);

        $this->info("Compiled PDF: {$path}");

        return self::SUCCESS;
    }
}
