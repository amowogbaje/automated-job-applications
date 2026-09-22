<?php

namespace App\Services\Resume;

use App\Models\JobListing;
use App\Models\Resume;
use App\Services\AI\AiClientInterface;
use Illuminate\Support\Str;

class CoverLetterWriter
{
    public function __construct(private AiClientInterface $ai)
    {
    }

    /**
     * @return array{cover_letter: string, tailored_summary: ?string}|null
     */
    public function write(Resume $resume, JobListing $job, array $snapshot): ?array
    {
        $result = $this->ai->completeJson(
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

        if (! $result || empty($result['cover_letter'])) {
            return null;
        }

        return [
            'cover_letter' => $result['cover_letter'],
            'tailored_summary' => $result['tailored_summary'] ?? null,
        ];
    }
}
