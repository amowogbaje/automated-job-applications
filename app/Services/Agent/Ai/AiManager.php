<?php

namespace App\Services\Agent\Ai;

use App\Services\Agent\Providers\Failover;
use App\Services\Agent\Providers\InvalidModelOutput;
use App\Services\Agent\Providers\ProviderException;
use App\Services\Agent\Providers\ProviderHealth;
use App\Services\Agent\Providers\ProviderRegistry;
use App\Services\Agent\Research\JsonExtractor;

/** AI calls with automatic fallback (Gemini / Groq / DeepSeek in the order set on the Providers page). */
class AiManager
{
    public function __construct(private Failover $failover, private ProviderRegistry $registry, private ProviderHealth $health) {}

    public function activeNames(): array
    {
        return collect($this->registry->all('ai'))
            ->filter(fn ($p) => $p['enabled'] && $p['configured'])->pluck('name')->values()->all();
    }

    /** With $json = true the reply is parsed into ->data; unusable output (or a $validate string) moves on to the next provider. */
    public function complete(array $messages, bool $json = false, ?int $maxTokens = null, ?callable $validate = null): AiResponse
    {
        $r = $this->failover->run('ai', function (array $p) use ($messages, $json, $maxTokens, $validate) {
            $res = (new OpenAiCompatibleProvider($p))->chat($messages, array_filter(['max_tokens' => $maxTokens]));
            $data = null;
            if ($json) {
                $data = JsonExtractor::parse($res['content']);
                if ($data === null) {
                    throw new InvalidModelOutput('The reply was not valid JSON.', $res['usage']);
                }
                // Optional content check: a string result means "unusable", so the next provider is tried.
                if ($validate && is_string($why = $validate($data))) {
                    throw new InvalidModelOutput($why, $res['usage']);
                }
            }

            return ['content' => $res['content'], 'data' => $data, 'usage' => $res['usage']];
        });

        return new AiResponse(
            text: $r['out']['content'], provider: $r['provider'], model: $r['model'],
            inputTokens: (int) ($r['out']['usage']['prompt'] ?? 0), outputTokens: (int) ($r['out']['usage']['completion'] ?? 0),
            data: $r['out']['data'], attempts: $r['attempts'],
        );
    }

    /** Direct test of one provider (ignores order and cooldown); logged as a test call. */
    public function test(string $name): array
    {
        $p = $this->registry->find('ai', $name);
        if (! $p || ! $p['configured']) {
            return ['ok' => false, 'message' => 'No API key found in .env.'];
        }
        $start = microtime(true);
        try {
            $res = (new OpenAiCompatibleProvider($p))->chat([['role' => 'user', 'content' => 'Reply with the single word: ok']], ['max_tokens' => 20, 'temperature' => 0]);
            $ms = (int) ((microtime(true) - $start) * 1000);
            $this->health->record('ai', $name, $p['model'], 'ok', 200, $ms, $res['usage'], null, true);
            $this->health->clear('ai', $name);

            return ['ok' => true, 'message' => "Works: replied \"".mb_substr(trim($res['content']), 0, 40)."\" in {$ms} ms ({$p['model']})."];
        } catch (ProviderException|InvalidModelOutput $e) {
            $this->health->record('ai', $name, $p['model'], $e instanceof ProviderException ? $e->kind : 'invalid', $e instanceof ProviderException ? $e->status : 200, null, null, $e->getMessage(), true);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
