<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MyMemory: no signup. 5,000 chars/day anonymous, 50,000/day when an email is sent
 * in the `de` parameter. Each request is capped at 500 bytes, so callers must chunk
 * (TranslationService does). Language is auto-detected via langpair "Autodetect|en".
 */
class MyMemoryDriver implements TranslationDriver
{
    public const MAX_BYTES = 450; // 500-byte API limit, with margin for URL-encoding quirks

    public function name(): string
    {
        return 'mymemory';
    }

    public function available(): bool
    {
        return true;
    }

    public function remainingBudget(): int
    {
        if (BudgetTracker::isPaused('mymemory')) {
            return 0;
        }

        return max(0, (int) config('translation.mymemory.daily_char_budget') - BudgetTracker::used('mymemory', 'day'));
    }

    public function translate(string $text, string $to = 'en'): ?string
    {
        if (strlen($text) > 500) {
            return null; // caller should chunk first
        }

        try {
            $params = ['q' => $text, 'langpair' => 'Autodetect|' . $to];
            if (filled(config('translation.mymemory.email'))) {
                $params['de'] = config('translation.mymemory.email');
            }

            $res = Http::timeout(20)->acceptJson()->get('https://api.mymemory.translated.net/get', $params);
            if (! $res->successful()) {
                return null;
            }

            // MyMemory returns HTTP 200 with responseStatus 403/429 in the body when limits are hit.
            if ((int) $res->json('responseStatus', 200) !== 200) {
                // 429 = daily quota used up (it resets daily).
                if ((int) $res->json('responseStatus') === 429) {
                    BudgetTracker::exhaust('mymemory', 'day');
                }
                Log::warning('MyMemory refused', ['status' => $res->json('responseStatus'), 'details' => $res->json('responseDetails')]);

                return null;
            }

            $out = $res->json('responseData.translatedText');
            if (is_string($out) && str_contains($out, 'MYMEMORY WARNING')) {
                BudgetTracker::exhaust('mymemory', 'day'); // the quota notice comes back as the "translation"
                return null;
            }
            if (! is_string($out) || $out === '') {
                return null;
            }

            BudgetTracker::add('mymemory', 'day', mb_strlen($text));

            return html_entity_decode($out, ENT_QUOTES | ENT_HTML5);
        } catch (\Throwable $e) {
            Log::warning('MyMemory exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
