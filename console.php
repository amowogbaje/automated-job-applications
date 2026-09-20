<?php

use Illuminate\Support\Facades\Schedule;

// Runs the aggregator every hour, then processes results: auto-sends
// email applications and bundles web/form-apply jobs into a digest.
// Chained with ->then() so processing always runs against fresh data.
Schedule::command('jobs:fetch')
    ->hourly()
    ->withoutOverlapping()
    ->then(function () {
        \Illuminate\Support\Facades\Artisan::call('applications:process');
    });

// Optional: prune listings older than 14 days to keep the table small.
Schedule::call(function () {
    \App\Models\JobListing::where('posted_at', '<', now()->subDays(14))->delete();
})->daily();
