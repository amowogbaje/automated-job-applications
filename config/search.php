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
    ],

    'timeout' => (int) env('SEARCH_TIMEOUT', 30),
    'results_per_query' => (int) env('SEARCH_RESULTS_PER_QUERY', 5),
    'queries_per_run' => (int) env('SEARCH_QUERIES_PER_RUN', 3),
];
