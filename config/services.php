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
    ],

    'job_aggregator' => [
        // "Nice to have" — boosts match_score but doesn't gate a listing out
        'keywords' => env('JOB_KEYWORDS', 'laravel,php,full-stack,vue,react'),

        // "Must have" — a listing is only stored if it hits at least
        // min_required_matches of these. This is the real skill filter.
        'required_skills' => env('JOB_REQUIRED_SKILLS', 'laravel,php'),
        'min_required_matches' => env('JOB_MIN_REQUIRED_MATCHES', 1),

        // Any hit here drops the listing regardless of everything else —
        // use it to cut noise like "wordpress", "junior", "unpaid", etc.
        'excluded_keywords' => env('JOB_EXCLUDED_KEYWORDS', ''),

        // SAFETY: defaults to false so the first several runs only generate
        // drafts (visible at /drafts) instead of actually emailing employers.
        // Review a batch of drafts first, then flip this to true in .env.
        'auto_send_applications' => env('AUTO_SEND_APPLICATIONS', false),

        // Path on disk to your resume PDF, attached to auto-sent application emails.
        'resume_pdf_path' => env('RESUME_PDF_PATH'),

        // Where hourly digests of web/form-apply jobs get sent, and where
        // auto-sent application emails appear to come "from" (set MAIL_FROM_ADDRESS
        // in .env to your real address so replies land in your inbox).
        'digest_email' => env('MAIL_DIGEST_TO'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],


];
