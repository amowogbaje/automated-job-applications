<?php

/*
 * AI providers. All three speak the OpenAI "chat/completions" format, so one driver serves them.
 * API keys live ONLY in .env. Order, enabled flag and model can be changed in the app
 * (Settings -> Providers); those overrides are stored in the settings table.
 */
return [
    'providers' => [
        'gemini' => [
            'label' => 'Google Gemini',
            'driver' => 'openai',
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
            'key' => env('GEMINI_API_KEY'),
            'key_env' => 'GEMINI_API_KEY',
            'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            // Thinking models can sit silent for a minute. "low" keeps replies quick; set GEMINI_REASONING= (empty) to omit it.
            'extra' => array_filter(['reasoning_effort' => env('GEMINI_REASONING', 'low')]),
            'timeout' => (int) env('GEMINI_TIMEOUT', 45),
            'priority' => 1,
            'signup_url' => 'https://aistudio.google.com/apikey',
        ],
        'groq' => [
            'label' => 'Groq',
            'driver' => 'openai',
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'key' => env('GROQ_API_KEY'),
            'key_env' => 'GROQ_API_KEY',
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            'priority' => 2,
            'signup_url' => 'https://console.groq.com/keys',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'driver' => 'openai',
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'key' => env('DEEPSEEK_API_KEY'),
            'key_env' => 'DEEPSEEK_API_KEY',
            'model' => env('DEEPSEEK_MODEL', 'deepseek-flash'),
            'priority' => 3,
            'signup_url' => 'https://platform.deepseek.com/api_keys',
        ],
    ],

    'timeout' => (int) env('AI_TIMEOUT', 60),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 3000),
    // Free tiers have small per-minute token limits, so keep prompts compact.
    'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 14000),

    // Daily safety limits (editable in Settings -> Providers).
    'limits' => [
        'ai_calls_per_day' => (int) env('AI_CALLS_PER_DAY', 300),
        'search_calls_per_day' => (int) env('SEARCH_CALLS_PER_DAY', 150),
    ],
];
