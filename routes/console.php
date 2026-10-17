<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull fresh listings hourly, then (inside the command) score them against every account's chosen skills.
Schedule::command('jobs:fetch')->hourly()->withoutOverlapping();

// Email alerts are opt-in per account and only contain jobs matching that account's skills.
// Runs a few minutes after the fetch so the new matches are already stored.
Schedule::command('jobs:alerts')->hourlyAt(10)->withoutOverlapping();

// Keep the job_listings table small.
Schedule::command('jobs:prune')->daily();

// Translate non-English listings once (stored; the UI button only shows the stored text).
Schedule::command('jobs:translate')->everyFiveMinutes()->withoutOverlapping();

// Social job posts (X / Mastodon / Hacker News / ...) are merged into the same feed; fetching never emails.
Schedule::command('social:fetch')->everyThirtyMinutes()->withoutOverlapping();
