<?php

namespace App\Services\Resume;

use App\Models\JobListing;
use App\Models\Resume;
use App\Services\AI\AiClientInterface;
use Illuminate\Support\Str;

class ResumeTailor
{
    public function __construct(protected AiClientInterface $ai) {}

    /**
     * Returns a "snapshot" describing which of the candidate's REAL skills and
     * projects to lead with for this job, plus a tailored one-line headline.
     * Every skill/project name in the result is validated against what's
     * actually in the resume — anything the AI hallucinates gets dropped.
     */
    public function tailorFor(Resume $resume, JobListing $job): ?array
    {
        $allSkills = $resume->skills->pluck('name')->all();
        $allProjects = $resume->projects->pluck('name')->all();

        $result = $this->ai->completeJson(
            systemPrompt: 'You are tailoring which of a candidate\'s REAL skills and projects to lead with for one '
                . 'specific job — you are not writing new content. Only choose from the exact strings given in '
                . '"available_skills" and "available_projects"; never invent a new skill or project name. '
                . 'Return JSON with exactly these keys: "tailored_headline" (one line, <=100 chars, honestly '
                . 'reflecting the candidate\'s real background aimed at this role), "lead_skills" (array of 5-10 '
                . 'strings copied verbatim from available_skills, most relevant first), "lead_projects" (array of '
                . '0-3 strings copied verbatim from available_projects, most relevant first), "gap_notes" (string, '
                . '1 sentence on any honest gap between the candidate and this job, or empty string if none).',
            userPrompt: json_encode([
                'job_title' => $job->title,
                'company' => $job->company,
                'job_description' => Str::limit($job->description, 3000),
                'candidate_headline' => $resume->headline,
                'candidate_summary' => $resume->summary,
                'available_skills' => $allSkills,
                'available_projects' => $allProjects,
            ]),
            maxTokens: 600,
        );

        if (! $result) {
            return null;
        }

        // Guard against hallucination: only keep names that genuinely exist on the resume.
        $leadSkills = collect($result['lead_skills'] ?? [])
            ->filter(fn ($s) => in_array($s, $allSkills, true))
            ->values()->all();

        $leadProjects = collect($result['lead_projects'] ?? [])
            ->filter(fn ($p) => in_array($p, $allProjects, true))
            ->values()->all();

        return [
            'tailored_headline' => $result['tailored_headline'] ?? $resume->headline,
            'lead_skills' => $leadSkills ?: array_slice($allSkills, 0, 8),
            'lead_projects' => $leadProjects,
            'gap_notes' => $result['gap_notes'] ?? '',
        ];
    }
}
