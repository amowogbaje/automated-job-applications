<?php

namespace App\Services\Apply;

use App\Http\Controllers\JobApplyController;
use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\Resume;

/**
 * Jobs employers post on THIS site: the application is stored in-app and the employer is emailed.
 * No external key, no third party, and instant, so it's the most reliable auto-apply lane.
 */
class NativeApplyDriver implements ApplyDriver
{
    public function channel(): string
    {
        return 'native';
    }

    public function supports(JobListing $job): bool
    {
        return $job->isNative() && $job->apply_method === 'native' && $job->status === 'published'
            && (! $job->closes_at || $job->closes_at->isFuture());
    }

    public function submit(JobListing $job, ApplicationDraft $draft, Resume $resume, CareerProfile $profile, string $resumePdfPath): ApplyResult
    {
        try {
            $application = app(JobApplyController::class)->record($draft->user_id, $job, $resume->id, (string) $draft->cover_letter, $resumePdfPath);

            return ApplyResult::submitted((string) $application->id);
        } catch (\Throwable $e) {
            return ApplyResult::failed($e->getMessage());
        }
    }
}
