<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** DeepL API Free/Pro. Free keys end in ":fx" and use api-free.deepl.com. Source language is auto-detected. */
class DeepLDriver implements TranslationDriver
{
    public function name(): string
    {
        return 'deepl';
    }

    public function available(): bool
    {
        return filled(config('translation.deepl.key'));
    }

    public function remainingBudget(): int
    {
        if (BudgetTracker::isPaused('deepl')) {
            return 0;
        }

        return max(0, (int) config('translation.deepl.monthly_char_budget') - BudgetTracker::used('deepl', 'month'));
    }

    public function translate(string $text, string $to = 'en'): ?string
    {
        $key = (string) config('translation.deepl.key');
        $host = str_ends_with($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
        // DeepL wants a regional variant for English targets.
        $target = strtoupper($to) === 'EN' ? 'EN-US' : strtoupper($to);

        try {
            $res = Http::withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $key])
                ->timeout(25)->acceptJson()
                ->post($host . '/v2/translate', ['text' => [$text], 'target_lang' => $target, 'preserve_formatting' => true]);

            if (! $res->successful()) {
                // 456 = quota exceeded for the month; 429 = too many requests (back off briefly).
                if ($res->status() === 456) {
                    BudgetTracker::exhaust('deepl', 'month');
                } elseif ($res->status() === 429) {
                    BudgetTracker::pause('deepl', 5);
                }
                Log::warning('DeepL translate failed', ['status' => $res->status()]);

                return null;
            }

            $out = $res->json('translations.0.text');
            if (is_string($out) && $out !== '') {
                BudgetTracker::add('deepl', 'month', mb_strlen($text));

                return $out;
            }
        } catch (\Throwable $e) {
            Log::warning('DeepL translate exception', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
