<?php

namespace App\Console\Commands;

use App\Models\ApplicantProfile;
use App\Services\AI\AnthropicClient;
use Illuminate\Console\Command;

class ImportResumeProfile extends Command
{
    protected $signature = 'resume:import {path : Path to a plain-text or already-extracted resume file}';

    protected $description = 'Parse a resume text file into skills/summary/projects and store it as your applicant profile';

    public function handle(AnthropicClient $ai): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        // Note: this expects plain text. If your resume is a PDF/DOCX, extract
        // the text first (e.g. `pdftotext resume.pdf resume.txt` on most Linux
        // systems, or a package like smalot/pdfparser) and pass the .txt file here.
        $rawText = file_get_contents($path);

        $this->info('Extracting skills, summary, and projects with AI...');

        $extracted = $ai->completeJson(
            systemPrompt: 'You are a resume-parsing assistant. Extract structured data from the resume text provided. '
                . 'Never invent skills, employers, or projects that are not present in the text. '
                . 'Return JSON with exactly these keys: '
                . '"skills" (array of strings, technical skills only), '
                . '"summary" (2-3 sentence professional summary in third person), '
                . '"projects" (array of objects with "name", "description", "tech" keys).',
            userPrompt: $rawText,
            maxTokens: 2048,
        );

        if (! $extracted) {
            $this->error('AI extraction failed or returned invalid JSON. Check your ANTHROPIC_API_KEY and try again.');
            $this->line('Falling back to storing raw resume text only — you can add skills/summary manually via tinker.');
            $extracted = ['skills' => [], 'summary' => null, 'projects' => []];
        }

        $profile = ApplicantProfile::current();
        $profile->fill([
            'resume_raw_text' => $rawText,
            'skills' => $extracted['skills'] ?? [],
            'summary' => $extracted['summary'] ?? null,
            'projects' => $extracted['projects'] ?? [],
        ]);
        $profile->save();

        $this->info('Profile saved. Skills found: ' . implode(', ', $extracted['skills'] ?? []));

        return self::SUCCESS;
    }
}
