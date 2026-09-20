<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agnes AI (apihub.agnes-ai.com) — a free, OpenAI-compatible API gateway.
 * Register for a key at https://agnes-ai.com (no card required). Free tier
 * is rate-limited (check their current docs for the exact RPM) but has no
 * token/credit ceiling as of this writing.
 *
 * This is bound specifically for the resume pipeline (ResumeParser,
 * ResumeTailor) in AppServiceProvider — cover letters / emails still use
 * whatever AI_PROVIDER is set to. Change RESUME_AI_PROVIDER in .env if you
 * want the resume work on a different provider instead.
 */
class AgnesClient implements AiClientInterface
{
    protected string $endpoint = 'https://apihub.agnes-ai.com/v1/chat/completions';

    public function __construct(
        protected ?string $apiKey = null,
        protected string $model = 'agnes-2.0-flash',
    ) {
        $this->apiKey ??= config('services.agnes.api_key');
        $this->model = config('services.agnes.model', $this->model);
    }

    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?string
    {
        if (! $this->apiKey) {
            Log::warning('Agnes AI API key not configured — skipping AI call.');
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
                Log::warning('Agnes AI API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Throwable $e) {
            Log::warning('Agnes AI API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function completeJson(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?array
    {
        if (! $this->apiKey) {
            Log::warning('Agnes AI API key not configured — skipping AI call.');
            return null;
        }

        $strictSystem = $systemPrompt . "\n\nRespond with ONLY valid JSON. No markdown fences, no preamble, no commentary.";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $strictSystem],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Agnes AI API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('choices.0.message.content');

            if (! $text) {
                return null;
            }

            // Agnes's OpenAI-compat layer doesn't guarantee a strict JSON mode
            // like Groq's response_format, so lean harder on prompt + cleanup here.
            $clean = trim(preg_replace('/^```(json)?|```$/m', '', $text));
            $decoded = json_decode($clean, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Agnes AI API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
