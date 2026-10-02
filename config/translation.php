<?php

/*
|--------------------------------------------------------------------------
| Job translation
|--------------------------------------------------------------------------
| Non-English listings (e.g. German ones from Arbeitnow) are translated ONCE,
| right after they're fetched and again every 5 minutes for anything still
| pending, and the result is stored on the listing. The "Show translation"
| button in the UI only swaps the stored text, so a click never costs quota.
|
| Drivers are tried in order; a driver that is unconfigured, over its quota
| or failing is skipped and the next one is used.
*/

return [
    'target' => 'en',

    // Order matters. Remove any you don't want.
    'drivers' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRANSLATE_DRIVERS', 'deepl,azure,libretranslate,mymemory,ai'))))),

    // Only the first N characters of a description are translated (title always is).
    'max_chars_per_job' => (int) env('TRANSLATE_MAX_CHARS_PER_JOB', 3500),
    // How many listings one run translates (the scheduler runs every 5 minutes).
    'per_run' => (int) env('TRANSLATE_PER_RUN', 15),
    'max_attempts' => 3,

    // DeepL API Free: needs a (free) key. A key ending in ":fx" uses api-free.deepl.com.
    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
        'monthly_char_budget' => (int) env('DEEPL_MONTHLY_BUDGET', 450000), // free plan is 500K; keep a margin
    ],

    // Azure AI Translator, free F0 tier = 2,000,000 chars/month (Azure sign-up needs a card; choose "Free F0").
    'azure' => [
        'key' => env('AZURE_TRANSLATOR_KEY'),
        'region' => env('AZURE_TRANSLATOR_REGION'),   // e.g. westeurope; leave empty for a "Global" resource
        'endpoint' => env('AZURE_TRANSLATOR_ENDPOINT', 'https://api.cognitive.microsofttranslator.com'),
        'monthly_char_budget' => (int) env('AZURE_MONTHLY_BUDGET', 1800000), // free tier is 2M; keep a margin
    ],

    // LibreTranslate: no limits when self-hosted (docker run libretranslate/libretranslate).
    // The public libretranslate.com instance needs a paid API key.
    'libretranslate' => [
        'url' => env('LIBRETRANSLATE_URL'),       // e.g. http://localhost:5000
        'key' => env('LIBRETRANSLATE_API_KEY'),
    ],

    // MyMemory: no signup. 5,000 chars/day anonymous, 50,000/day when you pass an email.
    // Hard limit of 500 bytes per request, so text is chunked.
    'mymemory' => [
        'email' => env('MYMEMORY_EMAIL'),
        'daily_char_budget' => (int) env('MYMEMORY_DAILY_BUDGET', env('MYMEMORY_EMAIL') ? 45000 : 4500),
    ],

    // Last resort: your configured AI provider (costs tokens). Remove "ai" from drivers to disable.
    'ai' => [
        'monthly_char_budget' => (int) env('AI_TRANSLATE_MONTHLY_BUDGET', 200000),
    ],
];
