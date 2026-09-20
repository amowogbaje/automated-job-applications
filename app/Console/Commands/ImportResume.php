<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Resume\PdfTextExtractor;
use App\Services\Resume\ResumeParser;
use App\Services\Resume\WebsiteResumeScraper;
use Illuminate\Console\Command;

class ImportResume extends Command
{
    protected $signature = 'resume:import
        {--pdf= : Path to your resume PDF}
        {--website= : Your personal/portfolio site URL, e.g. amowogbaje.com (tone/summary context only — never a source of facts)}
        {--user= : User ID or email to own this resume. Defaults to the only/first user account}
        {--no-activate : Store this import without making it the active resume}';

    protected $description = 'Parse your resume PDF and/or personal website into the resumes database tables (does not touch any PDF on disk)';

    public function handle(PdfTextExtractor $pdfExtractor, WebsiteResumeScraper $scraper, ResumeParser $parser): int
    {
        $pdfPath = $this->option('pdf');
        $websiteUrl = $this->option('website');

        if (! $pdfPath && ! $websiteUrl) {
            $this->error('Provide at least --pdf=path/to/resume.pdf or --website=yoursite.com (or both).');
            return self::FAILURE;
        }

        $pdfText = null;
        $websiteText = null;

        if ($pdfPath) {
            $this->info("Extracting text from {$pdfPath}...");
            try {
                $pdfText = $pdfExtractor->extract($pdfPath);
                $this->line('  ' . strlen($pdfText) . ' characters extracted.');
            } catch (\Throwable $e) {
                $this->error("  {$e->getMessage()}");
                if (! $websiteUrl) {
                    return self::FAILURE;
                }
            }
        }

        if ($websiteUrl) {
            $this->info("Fetching {$websiteUrl}...");
            try {
                $websiteText = $scraper->scrape($websiteUrl);
                $this->line('  ' . strlen($websiteText) . ' characters fetched (used for tone/summary only, never facts).');
            } catch (\Throwable $e) {
                $this->warn("  {$e->getMessage()} — continuing without website context.");
            }
        }

        if (! $pdfText && ! $websiteText) {
            $this->error('Nothing usable was extracted from either source. Nothing was saved.');
            return self::FAILURE;
        }

        $userId = null;
        if ($userOpt = $this->option('user')) {
            $user = is_numeric($userOpt) ? User::find($userOpt) : User::where('email', $userOpt)->first();
            if (! $user) {
                $this->error("No user found matching --user={$userOpt}.");
                return self::FAILURE;
            }
            $userId = $user->id;
        }

        $this->info('Structuring your resume with AI (skills, experience, projects, education)...');

        ['resume' => $resume, 'raw' => $raw] = $parser->parseAndStore(
            pdfText: $pdfText,
            websiteText: $websiteText,
            activate: ! $this->option('no-activate'),
            userId: $userId,
        );

        if (! $resume) {
            $this->error(
                'AI structuring failed or returned invalid JSON. Check RESUME_AI_PROVIDER / AGNES_API_KEY '
                . '(or GROQ_API_KEY / ANTHROPIC_API_KEY if you switched the resume provider) in .env and try again. '
                . 'Nothing was saved.'
            );
            return self::FAILURE;
        }

        $this->info("Saved resume #{$resume->id} for {$resume->full_name}.");
        $this->line('  Experiences: ' . $resume->experiences->count());
        $this->line('  Education: ' . $resume->education->count());
        $this->line('  Skills: ' . $resume->skills->count() . ' across ' . $resume->skillsByCategory()->count() . ' categories');
        $this->line('  Projects: ' . $resume->projects->count());
        $this->line('  Certifications: ' . $resume->certifications->count());
        $this->line($this->option('no-activate') ? '  (Stored but NOT activated — pass without --no-activate to make it live.)' : '  Set as your active resume.');
        $this->line('Run `php artisan resume:compile` to render this back out as a PDF.');

        return self::SUCCESS;
    }
}
