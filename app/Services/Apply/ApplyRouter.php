<?php

namespace App\Services\Apply;

use App\Models\JobListing;

/**
 * Picks the submission channel for a job. Order = most reliable first:
 * official ATS API (you hold the employer's key) -> third-party apply
 * service -> none (job stays in the digest for a manual apply).
 */
class ApplyRouter
{
    /** @var ApplyDriver[] */
    private array $drivers;

    public function __construct(NativeApplyDriver $native, GreenhouseApiDriver $greenhouse, LeverApiDriver $lever, ApplyServiceDriver $service)
    {
        // Jobs posted on this site first (instant, no third party), then official ATS keys, then the service.
        $this->drivers = [$native, $greenhouse, $lever, $service];
    }

    public function driverFor(JobListing $job): ?ApplyDriver
    {
        foreach ($this->drivers as $driver) {
            if ($driver->supports($job)) {
                return $driver;
            }
        }

        return null;
    }
}
