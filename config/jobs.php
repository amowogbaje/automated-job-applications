<?php

return [
    // Nothing older than this is fetched, matched, shown or kept. Capped at 30 days.
    'max_age_days' => min(30, max(1, (int) env('JOBS_MAX_AGE_DAYS', 30))),
];
