<?php

namespace App\Services\Agent\Ai;

class AiResponse
{
    public function __construct(
        public string $text,
        public string $provider,
        public ?string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?array $data = null,
        public array $attempts = [],
    ) {}
}
