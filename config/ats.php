<?php

/*
|--------------------------------------------------------------------------
| ATS boards + auto-apply settings
|--------------------------------------------------------------------------
| Reading jobs from Greenhouse / Lever / Ashby needs NO key — their public
| job-board feeds are open. Submitting an application through those same
| vendors' official APIs needs a key issued by the EMPLOYER, so it only
| works for boards you hold a key for (see integration.md §3).
*/

return [

    // "greenhouse:stripe,lever:spotify,ashby:linear" — vendor:board-slug
    // (the slug is the path segment in boards.greenhouse.io/{slug},
    // jobs.lever.co/{slug}, jobs.ashbyhq.com/{slug}). Per-user boards from
    // /profile are merged on top of these.
    'boards' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATS_BOARDS', ''))))),

    // Employer-issued keys, only if you have them: "board-slug:key,other:key"
    'greenhouse_keys' => env('GREENHOUSE_BOARD_KEYS', ''),
    'lever_keys' => env('LEVER_SITE_KEYS', ''),

    'auto_apply' => [
        // Hard ceiling across all accounts per run, on top of each account's own daily cap.
        'max_per_run' => (int) env('AUTO_APPLY_MAX_PER_RUN', 10),
        // Only jobs posted within this many hours are considered.
        'max_age_hours' => (int) env('AUTO_APPLY_MAX_AGE_HOURS', 72),
        // Master switch: when false every submission is a dry run (drafts only).
        'live' => (bool) env('AUTO_APPLY_LIVE', false),
    ],
];
