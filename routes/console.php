<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jobs are browsed in the app (/jobs), so nothing here emails you job updates any more:
// the hourly "digest" (applications:process) was removed from the schedule and its email code deleted.
// Pull fresh listings hourly. withoutOverlapping() guards against a slow run bumping into the next tick.
Schedule::command('jobs:fetch')
    ->hourly()
    ->withoutOverlapping()
    ->after(function () {
        // ATS / apply-service lane. Safe by default: drafts only until
        // AUTO_APPLY_LIVE=true AND the account is in "auto" mode at /profile.
        Artisan::call('applications:auto-apply');
    });

// Keep the job_listings table small; skips anything with a draft still
// awaiting your review so you never lose a draft out from under yourself.
Schedule::command('jobs:prune')->daily();

// Translate non-English listings once (stored; the UI button only shows the stored text).
Schedule::command('jobs:translate')->everyFiveMinutes()->withoutOverlapping();

// Social job posts (X / Mastodon / Hacker News / ...) land on the Social page; nothing is emailed.
Schedule::command('social:fetch')->everyThirtyMinutes()->withoutOverlapping();
