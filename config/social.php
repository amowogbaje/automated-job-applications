<?php

/*
|--------------------------------------------------------------------------
| Social job posts (X, Mastodon, Hacker News)
|--------------------------------------------------------------------------
| Searches are built from the CORE skills in your accounts' profiles, so only
| posts relevant to someone on the app are even considered. Official APIs only:
| no scraping of X/LinkedIn/Facebook (their terms prohibit it and accounts get banned).
*/

return [
    // hackernews: free, no key ("Ask HN: Who is hiring?" threads, lots of "email us" posts)
    // mastodon:   free, no key (public hashtag timelines)
    // bluesky:    free, but needs a free account + APP PASSWORD (search requires login)
    // lemmy:      free, no key (public API on any instance)
    // reddit:     official API, free for NON-COMMERCIAL use but needs a registered (and possibly approved) app
    // github:     only the repos YOU list where people post openings as issues (GitHub has no jobs feed)
    // x:          needs an X API token and is billed per post read (see below)
    'sources' => array_values(array_filter(array_map('trim', explode(',', (string) env('SOCIAL_SOURCES', 'hackernews,mastodon,bluesky,lemmy'))))),

    // How far back each run looks (also the maximum of the day filter's useful range).
    'lookback_hours' => (int) env('SOCIAL_LOOKBACK_HOURS', 48),

    // AI extraction is the only paid-per-token step: cap it per run.
    'ai_per_run' => (int) env('SOCIAL_AI_PER_RUN', 20),
    // Posts below this AI confidence are kept as "link only" and never emailed.
    'min_confidence' => (float) env('SOCIAL_MIN_CONFIDENCE', 0.6),

    // Same address is never auto-emailed twice by one account within this many days.
    'email_cooldown_days' => (int) env('SOCIAL_EMAIL_COOLDOWN_DAYS', 14),

    'mastodon' => [
        'instances' => array_values(array_filter(array_map('trim', explode(',', (string) env('MASTODON_INSTANCES', 'mastodon.social,fosstodon.org'))))),
        // Always checked, then filtered for hiring wording AND your skills.
        'hashtags' => array_values(array_filter(array_map('trim', explode(',', (string) env('MASTODON_HASHTAGS', 'hiring,fedihire,remotejobs,jobs,techjobs'))))),
        'per_tag' => 40,
    ],

    // Free. Handle like "you.bsky.social" + an APP PASSWORD from Bluesky settings (not your real password).
    'bluesky' => [
        'identifier' => env('BLUESKY_HANDLE'),
        'app_password' => env('BLUESKY_APP_PASSWORD'),
        'max_queries_per_run' => (int) env('BLUESKY_MAX_QUERIES', 6),
        'per_query' => 40,
    ],

    'lemmy' => [
        'instances' => array_values(array_filter(array_map('trim', explode(',', (string) env('LEMMY_INSTANCES', 'lemmy.world,programming.dev'))))),
        // Optional: specific communities as name@instance, e.g. "jobs@lemmy.world".
        'communities' => array_values(array_filter(array_map('trim', explode(',', (string) env('LEMMY_COMMUNITIES', ''))))),
        'max_skill_queries' => 5,
    ],

    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        // Reddit requires a unique, descriptive User-Agent: "platform:app-id:version (by /u/yourname)".
        'user_agent' => env('REDDIT_USER_AGENT', 'web:job-feed:1.0 (by /u/your-reddit-username)'),
        'subreddits' => array_values(array_filter(array_map('trim', explode(',', (string) env('REDDIT_SUBREDDITS', 'forhire'))))),
    ],

    'github' => [
        'repos' => array_values(array_filter(array_map('trim', explode(',', (string) env('GITHUB_JOB_REPOS', ''))))),
        'token' => env('GITHUB_TOKEN'),
    ],

    'hackernews' => [
        'max_threads' => 1,       // newest monthly "Who is hiring?" thread(s)
        'max_pages' => 3,         // 100 comments per page
    ],

    // X API v2 recent search (last 7 days). Billing is per post read, so there's a hard daily budget.
    'x' => [
        'bearer_token' => env('X_BEARER_TOKEN'),
        'daily_read_budget' => (int) env('X_DAILY_READ_BUDGET', 150),  // posts per day
        'max_results' => (int) env('X_MAX_RESULTS', 25),               // per query (API range 10-100)
        'max_queries_per_run' => (int) env('X_MAX_QUERIES_PER_RUN', 2),
        // The scheduler runs social:fetch every 30 minutes for the free sources; X is throttled separately
        // so the daily budget covers the whole day instead of being gone by breakfast.
        'min_interval_minutes' => (int) env('X_MIN_INTERVAL_MINUTES', 240),
        'language' => env('X_LANGUAGE', 'en'),
    ],
];
