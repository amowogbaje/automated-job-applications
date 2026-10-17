<?php

namespace App\Services\AI;

use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Providers\AllProvidersFailed;
use Illuminate\Support\Facades\Log;

/**
 * Lets the existing cover-letter / resume / translation code use the Gemini → Groq → DeepSeek failover
 * (order, cooldowns and keys are managed at Agent → Providers). Select with AI_PROVIDER=failover.
 * Like the other clients it returns null on failure instead of throwing.
 */
class FailoverClient implements AiClientInterface
{
    public function __construct(private AiManager $ai) {}

    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?string
    {
        try {
            return $this->ai->complete($this->messages($systemPrompt, $userPrompt), false, $maxTokens)->text;
        } catch (AllProvidersFailed $e) {
            Log::warning('All AI providers failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function completeJson(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?array
    {
        try {
            return $this->ai->complete($this->messages($systemPrompt, $userPrompt), true, $maxTokens)->data;
        } catch (AllProvidersFailed $e) {
            Log::warning('All AI providers failed (json)', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function messages(string $system, string $user): array
    {
        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]];
    }
}
