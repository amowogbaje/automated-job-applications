<?php

namespace App\Services\Agent\Providers;

use App\Models\ProviderCall;
use Illuminate\Support\Facades\Cache;

/** Cooldowns (cache) and the call log (database). */
class ProviderHealth
{
    private function key(string $kind, string $name): string
    {
        return "provider_cooldown:{$kind}:{$name}";
    }

    /** @return array{until:int,reason:string}|null */
    public function cooldown(string $kind, string $name): ?array
    {
        $c = Cache::get($this->key($kind, $name));

        return ($c && $c['until'] > time()) ? $c : null;
    }

    public function trip(string $kind, string $name, int $seconds, string $reason): void
    {
        Cache::put($this->key($kind, $name), ['until' => time() + $seconds, 'reason' => $reason], $seconds);
    }

    public function clear(string $kind, string $name): void
    {
        Cache::forget($this->key($kind, $name));
    }

    public function record(string $kind, string $provider, ?string $model, string $status, ?int $http, ?int $ms, ?array $usage, ?string $error, bool $isTest = false): void
    {
        ProviderCall::create([
            'kind' => $kind,
            'provider' => $provider,
            'model' => $model,
            'status' => $status,
            'http_status' => $http,
            'latency_ms' => $ms,
            'prompt_tokens' => (int) ($usage['prompt'] ?? 0),
            'completion_tokens' => (int) ($usage['completion'] ?? 0),
            'error' => $error ? mb_substr($error, 0, 1000) : null,
            'is_test' => $isTest,
        ]);
    }

    public function todayCount(string $kind): int
    {
        return ProviderCall::where('kind', $kind)->where('is_test', false)
            ->where('created_at', '>=', now()->startOfDay())->count();
    }

    /** Last 24h per provider: ok / failed counts. */
    public function stats(string $kind): array
    {
        $rows = ProviderCall::where('kind', $kind)->where('is_test', false)
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw("provider, SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) as ok, SUM(CASE WHEN status <> 'ok' THEN 1 ELSE 0 END) as failed, SUM(prompt_tokens + completion_tokens) as tokens")
            ->groupBy('provider')->get();

        return $rows->keyBy('provider')->map(fn ($r) => [
            'ok' => (int) $r->ok, 'failed' => (int) $r->failed, 'tokens' => (int) $r->tokens,
        ])->all();
    }
}
