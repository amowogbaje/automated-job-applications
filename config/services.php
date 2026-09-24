<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    /**
     * Add these arrays into your existing config/services.php file
     * (inside the top-level return [ ... ] array).
     */

    'adzuna' => [
        'app_id' => env('ADZUNA_APP_ID'),
        'app_key' => env('ADZUNA_APP_KEY'),
        'country' => env('ADZUNA_COUNTRY', 'gb'), // e.g. gb, us, ng, de...
        // Fetch-breadth only (what Adzuna's search API is queried for),
        // not per-user relevance — that's set per account at /profile.
        'search_term' => env('ADZUNA_SEARCH_TERM', 'software developer'),
    ],

    'job_aggregator' => [
        // Per-user job matching (keywords/required skills/exclusions/match
        // threshold), digest recipient, and auto-send are all per-account
        // now — see App\Models\CareerProfile and /profile. JOB_KEYWORDS,
        // AUTO_SEND_APPLICATIONS, and MAIL_DIGEST_TO are no longer read
        // from .env; safe to remove them from yours.

        // Fallback only: used if compiling a fresh PDF from the `resumes`
        // database tables fails for some reason. Normally every send
        // attaches a freshly compiled, job-tailored PDF instead — see
        // App\Services\Resume\ResumeCompiler.
        'resume_pdf_path' => env('RESUME_PDF_PATH'),
    ],

    // Which AiClientInterface implementation AppServiceProvider binds by
    // default (used for cover letters / application emails). 'resume_provider'
    // is a separate override just for the resume pipeline (ResumeParser,
    // ResumeTailor) — defaults to 'agnes' since it's free with no card.
    'ai' => [
        'provider' => env('AI_PROVIDER', 'groq'),
        'resume_provider' => env('RESUME_AI_PROVIDER', 'agnes'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        // llama-3.3-70b-versatile is the best free-tier quality/speed balance
        // as of this writing. llama-3.1-8b-instant is faster with a higher
        // free rate limit if you hit throttling on a big batch run.
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],

    'agnes' => [
        // Free, no card required — register at https://agnes-ai.com.
        'api_key' => env('AGNES_API_KEY'),
        'model' => env('AGNES_MODEL', 'agnes-2.0-flash'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    // Company/contact discovery for the leads pipeline. Free tier: 25
    // Discover calls/month, 50 searches/month. Get a key at hunter.io —
    // without one, /leads/discover just tells you to set it, nothing breaks.
    'hunter' => [
        'key' => env('HUNTER_API_KEY'),
    ],

];
