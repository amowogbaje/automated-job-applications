<?php

/* Search providers, tried in priority order with automatic fallback. Keys live only in .env. */
return [
    'providers' => [
        'tavily' => [
            'label' => 'Tavily',
            'driver' => 'tavily',
            'key' => env('TAVILY_API_KEY'),
            'key_env' => 'TAVILY_API_KEY',
            'priority' => 1,
            'signup_url' => 'https://app.tavily.com',
        ],
        'brave' => [
            'label' => 'Brave Search',
            'driver' => 'brave',
            'key' => env('BRAVE_SEARCH_API_KEY'),
            'key_env' => 'BRAVE_SEARCH_API_KEY',
            'priority' => 2,
            'signup_url' => 'https://brave.com/search/api/',
        ],
        'serper' => [
            'label' => 'Serper (Google results)',
            'driver' => 'serper',
            'key' => env('SERPER_API_KEY'),
            'key_env' => 'SERPER_API_KEY',
            'priority' => 3,
            'signup_url' => 'https://serper.dev',
        ],
        'exa' => [
            'label' => 'Exa',
            'driver' => 'exa',
            'key' => env('EXA_API_KEY'),
            'key_env' => 'EXA_API_KEY',
            'priority' => 4,
            'signup_url' => 'https://dashboard.exa.ai',
        ],
        'serpapi' => [
            'label' => 'SerpApi (Google results)',
            'driver' => 'serpapi',
            'key' => env('SERPAPI_API_KEY'),
            'key_env' => 'SERPAPI_API_KEY',
            'priority' => 5,
            'signup_url' => 'https://serpapi.com',
        ],
        'firecrawl' => [
            'label' => 'Firecrawl',
            'driver' => 'firecrawl',
            'key' => env('FIRECRAWL_API_KEY'),
            'key_env' => 'FIRECRAWL_API_KEY',
            'priority' => 6,
            'signup_url' => 'https://www.firecrawl.dev',
        ],
        'jina' => [
            'label' => 'Jina Search',
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
            'key_env' => 'JINA_API_KEY',
            'priority' => 7,
            'signup_url' => 'https://jina.ai',
        ],
        'searxng' => [
            'label' => 'SearXNG (self-hosted, URL not key)',
            'driver' => 'searxng',
            'key' => env('SEARXNG_URL'),
            'key_env' => 'SEARXNG_URL',
            'priority' => 8,
            'signup_url' => 'https://docs.searxng.org',
        ],
    ],

    'timeout' => (int) env('SEARCH_TIMEOUT', 30),
    'results_per_query' => (int) env('SEARCH_RESULTS_PER_QUERY', 5),
    'queries_per_run' => (int) env('SEARCH_QUERIES_PER_RUN', 3),
];
