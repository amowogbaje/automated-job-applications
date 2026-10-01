<?php

namespace App\Services\Apply;

use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\Resume;
use App\Services\JobSources\AtsBoardRegistry;
use Illuminate\Support\Facades\Http;

/**
 * Greenhouse Job Board API: POST /v1/boards/{board}/jobs/{id}, HTTP Basic with
 * the board's Job Board API key as username and no password. That key is issued
 * by the EMPLOYER (Greenhouse admin > Dev Center), so this driver only supports
 * boards listed in GREENHOUSE_BOARD_KEYS.
 */
class GreenhouseApiDriver implements ApplyDriver
{
    private const STANDARD = ['first_name', 'last_name', 'email', 'phone', 'resume', 'resume_text', 'cover_letter', 'cover_letter_text'];

    public function channel(): string
    {
        return 'greenhouse_api';
    }

    private function key(JobListing $job): ?string
    {
        return AtsBoardRegistry::keys('greenhouse_keys')[strtolower((string) $job->ats_board)] ?? null;
    }

    public function supports(JobListing $job): bool
    {
        return $job->ats === 'greenhouse' && $job->ats_board && $job->ats_job_id && $this->key($job);
    }

    public function submit(JobListing $job, ApplicationDraft $draft, Resume $resume, CareerProfile $profile, string $resumePdfPath): ApplyResult
    {
        try {
            $base = "https://boards-api.greenhouse.io/v1/boards/{$job->ats_board}/jobs/{$job->ats_job_id}";

            $form = Http::timeout(20)->get($base, ['questions' => 'true']);
            if (! $form->successful()) {
                return ApplyResult::failed("Could not load application form (HTTP {$form->status()})");
            }

            [$first, $last] = $this->splitName($resume->full_name);
            $fields = [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $resume->email,
                'phone' => $resume->phone,
                'cover_letter_text' => $draft->cover_letter,
            ];

            $screening = new ScreeningAnswers($profile->screening_answers ?? []);
            $unanswered = [];

            foreach ($form->json('questions', []) as $q) {
                $required = (bool) ($q['required'] ?? false);
                foreach ($q['fields'] ?? [] as $f) {
                    $name = $f['name'] ?? null;
                    if (! $name || in_array($name, self::STANDARD, true) || isset($fields[$name])) {
                        continue;
                    }
                    $answer = $screening->forLabel($q['label'] ?? '');

                    if ($answer !== null && in_array($f['type'] ?? '', ['multi_value_single_select', 'multi_value_multi_select'], true)) {
                        $answer = $this->matchOption($f['values'] ?? [], $answer);
                    }

                    if ($answer !== null) {
                        $fields[$name] = $answer;
                    } elseif ($required) {
                        $unanswered[] = $q['label'] ?? $name;
                    }
                }
            }

            if ($unanswered) {
                return ApplyResult::needsInput(array_values(array_unique($unanswered)));
            }

            $req = Http::withBasicAuth($this->key($job), '')->timeout(60)->asMultipart();
            $req = $req->attach('resume', file_get_contents($resumePdfPath), basename($resumePdfPath));

            $payload = [];
            foreach (array_filter($fields, fn ($v) => $v !== null && $v !== '') as $k => $v) {
                $payload[] = ['name' => $k, 'contents' => (string) $v];
            }

            $res = $req->post($base, $payload);

            return $res->successful()
                ? ApplyResult::submitted($job->ats_job_id)
                : ApplyResult::failed("Greenhouse rejected the application (HTTP {$res->status()}): " . $res->body());
        } catch (\Throwable $e) {
            return ApplyResult::failed($e->getMessage());
        }
    }

    private function splitName(?string $full): array
    {
        $parts = preg_split('/\s+/', trim((string) $full)) ?: [];

        return [array_shift($parts) ?? '', implode(' ', $parts)];
    }

    /** Map the user's free-text answer onto one of the question's real option values. */
    private function matchOption(array $values, string $answer): ?string
    {
        foreach ($values as $v) {
            if (strcasecmp(trim($v['label'] ?? ''), trim($answer)) === 0) {
                return (string) ($v['value'] ?? null);
            }
        }

        return null; // no exact option -> treated as unanswered
    }
}
