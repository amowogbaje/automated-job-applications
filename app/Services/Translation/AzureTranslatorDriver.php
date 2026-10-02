<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure AI Translator (Microsoft Translator), free F0 tier: 2 million characters/month.
 * Needs an Azure account (a card is required to sign up) and a Translator resource;
 * pick the Free F0 pricing tier. Source language is auto-detected.
 */
class AzureTranslatorDriver implements TranslationDriver
{
    public function name(): string
    {
        return 'azure';
    }

    public function available(): bool
    {
        return filled(config('translation.azure.key'));
    }

    public function remainingBudget(): int
    {
        if (BudgetTracker::isPaused('azure')) {
            return 0;
        }

        return max(0, (int) config('translation.azure.monthly_char_budget') - BudgetTracker::used('azure', 'month'));
    }

    public function translate(string $text, string $to = 'en'): ?string
    {
        try {
            $headers = ['Ocp-Apim-Subscription-Key' => (string) config('translation.azure.key')];
            // Regional (non-global) resources must also send their region.
            if (filled(config('translation.azure.region'))) {
                $headers['Ocp-Apim-Subscription-Region'] = (string) config('translation.azure.region');
            }

            $res = Http::withHeaders($headers)->timeout(25)->acceptJson()->asJson()
                ->post(rtrim((string) config('translation.azure.endpoint'), '/') . '/translate?api-version=3.0&to=' . urlencode($to) . '&textType=plain', [['Text' => $text]]);

            if (! $res->successful()) {
                // 403 with a quota message / 429 = out of free quota or throttled.
                if ($res->status() === 403 && stripos($res->body(), 'quota') !== false) {
                    BudgetTracker::exhaust('azure', 'month');
                } elseif (in_array($res->status(), [429, 403], true)) {
                    BudgetTracker::pause('azure', 10);
                }
                Log::warning('Azure Translator failed', ['status' => $res->status(), 'detail' => mb_substr($res->body(), 0, 160)]);

                return null;
            }

            $out = $res->json('0.translations.0.text');
            if (is_string($out) && $out !== '') {
                BudgetTracker::add('azure', 'month', mb_strlen($text));

                return $out;
            }
        } catch (\Throwable $e) {
            Log::warning('Azure Translator exception', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
