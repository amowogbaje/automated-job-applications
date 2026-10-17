<?php

namespace App\Services\Agent\Ai;

use App\Services\Agent\Providers\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Gemini (compat endpoint), Groq and DeepSeek all accept this request shape. */
class OpenAiCompatibleProvider
{
    public function __construct(private array $provider)
    {
    }

    /** @return array{content:string, usage:array{prompt:int,completion:int}} */
    public function chat(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $this->provider['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? config('ai.max_output_tokens', 3000),
        ];

        try {
            $response = Http::withToken((string) $this->provider['key'])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(config('ai.timeout', 60))
                ->post(rtrim($this->provider['base_url'], '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new ProviderException(ProviderException::NETWORK, 'Connection problem: '.mb_substr($e->getMessage(), 0, 200));
        }

        if ($response->failed()) {
            throw ProviderException::fromResponse($response);
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $usage = [
            'prompt' => (int) data_get($response->json(), 'usage.prompt_tokens', 0),
            'completion' => (int) data_get($response->json(), 'usage.completion_tokens', 0),
        ];

        if (! is_string($content) || trim($content) === '') {
            throw new \App\Services\Agent\Providers\InvalidModelOutput('The model returned an empty response.', $usage);
        }

        return ['content' => $content, 'usage' => $usage];
    }
}
