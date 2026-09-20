<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicClient implements AiClientInterface
{
    public function __construct(
        protected ?string $apiKey = null,
        protected string $model = 'claude-sonnet-4-6',
    ) {
        $this->apiKey ??= config('services.anthropic.api_key');
        $this->model = config('services.anthropic.model', $this->model);
    }

    /**
     * Send a single-turn prompt and get back the text response.
     * Returns null on failure so callers can skip gracefully rather than crash a batch job.
     */
    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?string
    {
        if (! $this->apiKey) {
            Log::warning('Anthropic API key not configured — skipping AI call.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Anthropic API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $blocks = $response->json('content', []);

            return collect($blocks)
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");
        } catch (\Throwable $e) {
            Log::warning('Anthropic API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Same as complete(), but asks for and parses strict JSON output.
     * Returns null if the model didn't return valid JSON.
     */
    public function completeJson(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?array
    {
        $strictSystem = $systemPrompt . "\n\nRespond with ONLY valid JSON. No markdown fences, no preamble, no commentary.";
        $text = $this->complete($strictSystem, $userPrompt, $maxTokens);

        if (! $text) {
            return null;
        }

        $clean = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $decoded = json_decode($clean, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
