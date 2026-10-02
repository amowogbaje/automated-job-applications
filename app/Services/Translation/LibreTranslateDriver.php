<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Self-hosted (or paid public) LibreTranslate. Unlimited when you host it yourself. */
class LibreTranslateDriver implements TranslationDriver
{
    public function name(): string
    {
        return 'libretranslate';
    }

    public function available(): bool
    {
        return filled(config('translation.libretranslate.url'));
    }

    public function remainingBudget(): int
    {
        return BudgetTracker::isPaused('libretranslate') ? 0 : PHP_INT_MAX;
    }

    public function translate(string $text, string $to = 'en'): ?string
    {
        try {
            $payload = ['q' => $text, 'source' => 'auto', 'target' => $to, 'format' => 'text'];
            if (filled(config('translation.libretranslate.key'))) {
                $payload['api_key'] = config('translation.libretranslate.key');
            }

            $res = Http::timeout(30)->acceptJson()->asJson()
                ->post(rtrim((string) config('translation.libretranslate.url'), '/') . '/translate', $payload);

            if (! $res->successful()) {
                if (in_array($res->status(), [429, 403], true)) {
                    BudgetTracker::pause('libretranslate', 15); // rate-limited or key rejected: stop hammering it
                }
                Log::warning('LibreTranslate failed', ['status' => $res->status()]);

                return null;
            }

            $out = $res->json('translatedText');

            return is_string($out) && $out !== '' ? $out : null;
        } catch (\Throwable $e) {
            Log::warning('LibreTranslate exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
