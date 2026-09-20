<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Free tier AI provider. Groq gives generous free rate limits on
 * fast open-weight models (Llama 3.x) with no card required to sign up —
 * get a key at https://console.groq.com/keys.
 *
 * Swap providers anytime via AI_PROVIDER in .env (see AppServiceProvider),
 * without touching any of the code that calls AiClientInterface.
 */
class GroqClient implements AiClientInterface
{
    protected string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        protected ?string $apiKey = null,
        protected string $model = 'llama-3.3-70b-versatile',
    ) {
        $this->apiKey ??= config('services.groq.api_key');
        $this->model = config('services.groq.model', $this->model);
    }

    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?string
    {
        if (! $this->apiKey) {
            Log::warning('Groq API key not configured — skipping AI call.');
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Groq API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Throwable $e) {
            Log::warning('Groq API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function completeJson(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?array
    {
        if (! $this->apiKey) {
            Log::warning('Groq API key not configured — skipping AI call.');
            return null;
        }

        $strictSystem = $systemPrompt . "\n\nRespond with ONLY valid JSON. No markdown fences, no preamble, no commentary.";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    // Groq's JSON mode — forces a syntactically valid JSON object back.
                    // (Requires the word "JSON" to appear in the prompt, which it does above.)
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $strictSystem],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Groq API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('choices.0.message.content');

            if (! $text) {
                return null;
            }

            $clean = trim(preg_replace('/^```(json)?|```$/m', '', $text));
            $decoded = json_decode($clean, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Groq API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
