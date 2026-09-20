<?php

namespace App\Services\AI;

interface AiClientInterface
{
    /**
     * Send a single-turn prompt and get back the text response.
     * Returns null on failure so callers can skip gracefully rather than crash a batch job.
     */
    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?string;

    /**
     * Same as complete(), but asks for and parses strict JSON output.
     * Returns null if the model didn't return valid JSON.
     */
    public function completeJson(string $systemPrompt, string $userPrompt, int $maxTokens = 1024): ?array;
}
