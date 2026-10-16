<?php

return [
    // 'queue' = run via the queue worker (cron). 'sync' = run during the web request
    // (fallback if cron isn't set up yet; may hit the host's execution time limit).
    'mode' => env('RESEARCH_RUN_MODE', 'queue'),

    'pages_per_site' => (int) env('RESEARCH_PAGES_PER_SITE', 4),

    // Page text sent to the AI per source, and the overall prompt budget (free tiers have small per-minute token limits).
    'page_chars' => (int) env('RESEARCH_PAGE_CHARS', 3500),
    'max_prompt_chars' => (int) env('RESEARCH_MAX_PROMPT_CHARS', 24000),
    'fetch_timeout' => (int) env('RESEARCH_FETCH_TIMEOUT', 12),

    // Sent with every page fetch so site owners can identify the bot.
    'user_agent' => 'OpportunityAgentBot/1.0 (personal research tool'
        .(env('RESEARCH_CONTACT_EMAIL') ? '; contact: '.env('RESEARCH_CONTACT_EMAIL') : '').')',
];
