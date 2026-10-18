<?php

return [
    // Caps that keep a run cheap on free search/AI tiers.
    'max_queries_per_run' => (int) env('DISCOVERY_MAX_QUERIES', 4),
    'results_per_query' => (int) env('DISCOVERY_RESULTS_PER_QUERY', 6),
    'max_candidates_per_run' => (int) env('DISCOVERY_MAX_CANDIDATES', 12),
    'ai_batch' => (int) env('DISCOVERY_AI_BATCH', 8),
    'runs_per_day' => (int) env('DISCOVERY_RUNS_PER_DAY', 12),
    // "Research new candidates automatically" never queues more than this many per run.
    'auto_research_cap' => (int) env('DISCOVERY_AUTO_RESEARCH_CAP', 5),

    // Optional daily scheduled run: off | daily. Uses every active service and the strategies below.
    'schedule' => env('DISCOVERY_SCHEDULE', 'off'),
    'schedule_strategies' => ['hiring', 'external_assistance'],

    // Pages that are never a company's own site and make poor evidence on their own.
    'junk_domains' => [
        'wikipedia.org', 'youtube.com', 'facebook.com', 'instagram.com', 'pinterest.com', 'quora.com',
        'tiktok.com', 'medium.com', 'glassdoor.com', 'indeed.com',
    ],

    // Query templates per strategy. Placeholders: {keyword} {skill} {target} {signal} {region}.
    // A template is only used when every placeholder it names has a value on the chosen service.
    'templates' => [
        'technical_need' => [
            '{target} company manual process needs {keyword}',
            '{target} {signal}',
            '{target} looking to automate with {skill}',
        ],
        'hiring' => [
            '{keyword} developer hiring {region}',
            'hiring {skill} developer contract {region}',
            '{skill} engineer vacancy {region}',
        ],
        'external_assistance' => [
            'request for proposal {keyword} {region}',
            'looking for freelance {keyword} developer {region}',
            'seeking development partner {skill}',
        ],
        'funded_growth' => [
            '{target} raises funding {region}',
            '{target} launches new platform {region}',
            '{target} expansion announcement {region}',
        ],
        'portfolio_fit' => [
            '{industry} companies need {technology} platform',
            '{industry} business {technology} integration',
        ],
    ],
];
