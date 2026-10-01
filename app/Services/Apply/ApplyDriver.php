<?php

namespace App\Services\Apply;

use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\Resume;

interface ApplyDriver
{
    /** Stored on application_drafts.apply_channel. */
    public function channel(): string;

    /** Can this driver submit THIS job right now (right ATS, key/credentials present)? */
    public function supports(JobListing $job): bool;

    /** Submit. Must never throw — return ApplyResult::failed() instead. */
    public function submit(JobListing $job, ApplicationDraft $draft, Resume $resume, CareerProfile $profile, string $resumePdfPath): ApplyResult;
}
