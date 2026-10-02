<?php

return [
    // Spam brakes for posting. Raise them once you've seen how the board is used.
    'max_active_jobs' => (int) env('EMPLOYER_MAX_ACTIVE_JOBS', 10),
    'max_publishes_per_day' => (int) env('EMPLOYER_MAX_PUBLISHES_PER_DAY', 5),

    // Listing lifetime options shown in the post-a-job form (days).
    'duration_options' => [14, 30, 60, 90],
    'default_duration' => 30,
];
