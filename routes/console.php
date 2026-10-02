<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull fresh listings hourly, then immediately run the auto-apply/digest split
// against whatever's new — per account now, not one shared run: each account
// with an active resume gets scored against its own career profile, and its
// own digest/auto-send settings from /profile. withoutOverlapping() guards
// against a slow run bumping into the next scheduled tick.
Schedule::command('jobs:fetch')
    ->hourly()
    ->withoutOverlapping()
    ->after(function () {
        Artisan::call('applications:process');
        // ATS / apply-service lane. Safe by default: drafts only until
        // AUTO_APPLY_LIVE=true AND the account is in "auto" mode at /profile.
        Artisan::call('applications:auto-apply');
    });

// Keep the job_listings table small; skips anything with a draft still
// awaiting your review so you never lose a draft out from under yourself.
Schedule::command('jobs:prune')->daily();

// Optional: uncomment to also auto-generate manual-review drafts (not just
// the email-apply/digest split above) for your top-scoring new jobs every run.
// Schedule::command('applications:generate')->hourly();

// Translate non-English listings once (stored; the UI button only shows the stored text).
// Matching listings only, so free-tier quotas last.
Schedule::command('jobs:translate')->everyFiveMinutes()->withoutOverlapping();

// Social job posts (X / Mastodon / Hacker News): matched to your skills, AI-extracted, then the
// same pipeline emails/drafts/alerts as board jobs. Every 30 min; X throttles itself (see config/social.php).
Schedule::command('social:fetch')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->after(function () {
        Artisan::call('applications:process');
    });
