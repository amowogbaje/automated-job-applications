<?php

namespace App\Services\Resume;

use App\Models\Resume;
use App\Services\AI\AiClientInterface;
use Illuminate\Support\Facades\DB;

class ResumeParser
{
    public function __construct(protected AiClientInterface $ai) {}

    /**
     * @param string|null $pdfText Extracted text from the uploaded resume PDF (the source of truth for facts).
     * @param string|null $websiteText Cleaned text from a personal site (tone/positioning only).
     * @param int|null $userId Owning user. Defaults to the logged-in user, then the only/first user (CLI context).
     * @return array{resume: ?Resume, raw: ?array} raw is the AI's parsed JSON, null on failure
     */
    public function parseAndStore(?string $pdfText, ?string $websiteText, bool $activate = true, ?int $userId = null): array
    {
        if (! $pdfText && ! $websiteText) {
            throw new \InvalidArgumentException('Provide at least one of pdfText or websiteText.');
        }

        $userId ??= auth()->id() ?? \App\Models\User::query()->value('id');

        $parsed = $this->ai->completeJson(
            systemPrompt: $this->systemPrompt(),
            userPrompt: json_encode([
                'resume_text' => $pdfText,
                'personal_website_text' => $websiteText,
            ]),
            maxTokens: 3000,
        );

        if (! $parsed) {
            return ['resume' => null, 'raw' => null];
        }

        $source = $pdfText && $websiteText ? 'merged' : ($pdfText ? 'pdf_upload' : 'website');

        $resume = DB::transaction(function () use ($parsed, $pdfText, $websiteText, $source, $userId) {
            $resume = Resume::create([
                'user_id' => $userId,
                'source' => $source,
                'is_active' => false, // activated below if requested, after we know it saved cleanly
                'full_name' => $parsed['full_name'] ?? 'Unknown',
                'headline' => $parsed['headline'] ?? null,
                'email' => $parsed['email'] ?? null,
                'phone' => $parsed['phone'] ?? null,
                'location' => $parsed['location'] ?? null,
                'website_url' => $parsed['website_url'] ?? null,
                'linkedin_url' => $parsed['linkedin_url'] ?? null,
                'github_url' => $parsed['github_url'] ?? null,
                'summary' => $parsed['summary'] ?? null,
                'raw_pdf_text' => $pdfText,
                'raw_website_text' => $websiteText,
            ]);

            foreach (($parsed['experiences'] ?? []) as $i => $exp) {
                $resume->experiences()->create([
                    'job_title' => $exp['job_title'] ?? '',
                    'company' => $exp['company'] ?? null,
                    'location' => $exp['location'] ?? null,
                    'start_date' => $this->safeDate($exp['start_date'] ?? null),
                    'end_date' => $this->safeDate($exp['end_date'] ?? null),
                    'is_current' => (bool) ($exp['is_current'] ?? false),
                    'bullets' => $exp['bullets'] ?? [],
                    'sort_order' => $i,
                ]);
            }

            foreach (($parsed['education'] ?? []) as $i => $edu) {
                $resume->education()->create([
                    'institution' => $edu['institution'] ?? '',
                    'degree' => $edu['degree'] ?? null,
                    'field' => $edu['field'] ?? null,
                    'start_date' => $this->safeDate($edu['start_date'] ?? null),
                    'end_date' => $this->safeDate($edu['end_date'] ?? null),
                    'sort_order' => $i,
                ]);
            }

            $skillOrder = 0;
            foreach (($parsed['skills'] ?? []) as $category => $names) {
                foreach ((array) $names as $name) {
                    $resume->skills()->create([
                        'category' => is_string($category) ? $category : 'other',
                        'name' => $name,
                        'sort_order' => $skillOrder++,
                    ]);
                }
            }

            foreach (($parsed['projects'] ?? []) as $i => $proj) {
                $resume->projects()->create([
                    'name' => $proj['name'] ?? '',
                    'description' => $proj['description'] ?? null,
                    'tech_stack' => $proj['tech_stack'] ?? [],
                    'url' => $proj['url'] ?? null,
                    'sort_order' => $i,
                ]);
            }

            foreach (($parsed['certifications'] ?? []) as $i => $cert) {
                $resume->certifications()->create([
                    'name' => is_array($cert) ? ($cert['name'] ?? '') : $cert,
                    'sort_order' => $i,
                ]);
            }

            return $resume;
        });

        if ($activate) {
            $resume->activate();
        }

        return ['resume' => $resume->fresh(['experiences', 'education', 'skills', 'projects', 'certifications']), 'raw' => $parsed];
    }

    protected function safeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null; // AI gave something unparseable (e.g. "Present") — leave null, is_current/end_date null covers it
        }
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
You are a resume-structuring assistant. You will receive JSON with two fields:
"resume_text" (the authoritative source of every fact — job history, dates,
skills, projects, education, contact details) and optionally
"personal_website_text" (a personal/portfolio site — use this ONLY to refine
tone, positioning, and the professional summary; NEVER pull a fact from it —
an employer, project, skill, or date — unless that same fact also appears in
resume_text).

Rules:
- Never invent or embellish anything not present in resume_text.
- If a field isn't present anywhere, omit it or use an empty array — do not guess.
- Dates should be "YYYY-MM-DD" (use the 1st of the month if only month/year is given) or omitted.
- Group skills into sensible categories such as "backend", "frontend", "database",
  "apis_integrations", "applied_ai", "testing_devops" — use "other" for anything
  that doesn't fit.

Return ONLY valid JSON with exactly this shape:
{
  "full_name": string,
  "headline": string,
  "email": string|null,
  "phone": string|null,
  "location": string|null,
  "website_url": string|null,
  "linkedin_url": string|null,
  "github_url": string|null,
  "summary": string,
  "experiences": [
    {"job_title": string, "company": string, "location": string|null,
     "start_date": string|null, "end_date": string|null, "is_current": boolean,
     "bullets": [string, ...]}
  ],
  "education": [
    {"institution": string, "degree": string|null, "field": string|null,
     "start_date": string|null, "end_date": string|null}
  ],
  "skills": { "category_name": [string, ...], ... },
  "projects": [
    {"name": string, "description": string, "tech_stack": [string, ...], "url": string|null}
  ],
  "certifications": [string, ...]
}
PROMPT;
    }
}
