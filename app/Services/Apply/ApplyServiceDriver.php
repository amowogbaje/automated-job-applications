<?php

namespace App\Services\Apply;

use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\Resume;
use Illuminate\Support\Facades\Http;

/**
 * Hands the job URL to a third-party "apply for me" API (The Boring Project's
 * Apply API shape: POST /sessions/apply, outcome arrives later by webhook as
 * submitted | needs_input | failed). This is the only route that covers ATSs
 * you hold no employer key for. It is asynchronous: submit() returns PENDING
 * and ApplyServiceWebhookController finishes the draft.
 *
 * Caveat: the provider applies with the candidate profile stored in ITS
 * dashboard, so the per-job tailored PDF/cover letter in this app is not what
 * gets filed unless the provider offers per-application overrides.
 */
class ApplyServiceDriver implements ApplyDriver
{
    public function channel(): string
    {
        return 'apply_service';
    }

    public function supports(JobListing $job): bool
    {
        return filled(config('services.apply_service.api_key'))
            && filled(config('services.apply_service.candidate_profile_id'))
            && filled($job->apply_url ?: $job->url);
    }

    public function submit(JobListing $job, ApplicationDraft $draft, Resume $resume, CareerProfile $profile, string $resumePdfPath): ApplyResult
    {
        try {
            $res = Http::withToken(config('services.apply_service.api_key'))
                ->timeout(30)
                ->acceptJson()
                ->post(rtrim(config('services.apply_service.base_url'), '/') . '/sessions/apply', [
                    'candidateProfileId' => config('services.apply_service.candidate_profile_id'),
                    'jobs' => [[
                        'companyName' => $job->company,
                        'title' => $job->title,
                        'jobId' => (string) ($job->ats_job_id ?: $job->external_id),
                        'link' => $job->apply_url ?: $job->url,
                    ]],
                ]);

            if (! $res->successful() || ! $res->json('sessionId')) {
                return ApplyResult::failed("Apply service error (HTTP {$res->status()}): " . $res->body());
            }

            return ApplyResult::pending((string) $res->json('sessionId'));
        } catch (\Throwable $e) {
            return ApplyResult::failed($e->getMessage());
        }
    }
}
