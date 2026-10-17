<?php

/*
|--------------------------------------------------------------------------
| ATS boards
|--------------------------------------------------------------------------
| Reading jobs from Greenhouse / Lever / Ashby needs NO key: their public job-board feeds are open.
| Which of their jobs reach you is still decided by YOUR chosen skills (Job interests).
*/

return [

    // "greenhouse:stripe,lever:spotify,ashby:linear" — vendor:board-slug
    // (the slug is the path segment in boards.greenhouse.io/{slug},
    // jobs.lever.co/{slug}, jobs.ashbyhq.com/{slug}). Per-user boards from
    // Job interests are merged on top of these.
    'boards' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATS_BOARDS', ''))))),
];
