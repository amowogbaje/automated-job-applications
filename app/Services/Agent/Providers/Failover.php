<?php

namespace App\Services\Agent\Providers;

/**
 * Runs a call against providers in priority order. On rate limits, server errors,
 * network problems or unusable output it moves to the next provider; providers that
 * trip a rate limit are put on cooldown so they are skipped until it expires.
 */
class Failover
{
    public function __construct(private ProviderRegistry $registry, private ProviderHealth $health)
    {
    }

    /**
     * @param  callable(array):array  $call  receives the provider row; returns ['usage' => [...]|null, ...payload]
     * @return array{out:array, provider:string, model:?string, attempts:array, latency_ms:int}
     */
    public function run(string $kind, callable $call, ?string $prefer = null): array
    {
        $this->guardDaily($kind);

        $attempts = [];
        $usable = [];
        foreach ($this->registry->all($kind) as $p) {
            if (! $p['enabled']) {
                continue;
            }
            if (! $p['configured']) {
                $attempts[] = ['provider' => $p['name'], 'outcome' => 'skipped', 'detail' => 'no API key in .env'];

                continue;
            }
            if ($cd = $this->health->cooldown($kind, $p['name'])) {
                $attempts[] = ['provider' => $p['name'], 'outcome' => 'cooling_down', 'detail' => $cd['reason'].' until '.date('H:i:s', $cd['until'])];

                continue;
            }
            $usable[] = $p;
        }

        // A preferred provider goes first when it is usable; the rest keep their order as the fallback.
        if ($prefer) {
            usort($usable, fn ($a, $b) => (int) ($b['name'] === $prefer) <=> (int) ($a['name'] === $prefer));
        }

        foreach ($usable as $p) {
            $start = microtime(true);
            try {
                $out = $call($p);
                $ms = (int) ((microtime(true) - $start) * 1000);
                $this->health->record($kind, $p['name'], $p['model'], 'ok', 200, $ms, $out['usage'] ?? null, null);

                return ['out' => $out, 'provider' => $p['name'], 'model' => $p['model'], 'attempts' => $attempts, 'latency_ms' => $ms];
            } catch (ProviderException $e) {
                $ms = (int) ((microtime(true) - $start) * 1000);
                $this->health->record($kind, $p['name'], $p['model'], $e->kind, $e->status, $ms, null, $e->getMessage());
                if ($seconds = $e->cooldownSeconds()) {
                    $this->health->trip($kind, $p['name'], $seconds, $e->kind);
                }
                $attempts[] = ['provider' => $p['name'], 'outcome' => $e->kind, 'detail' => $e->getMessage()];
            } catch (InvalidModelOutput $e) {
                $ms = (int) ((microtime(true) - $start) * 1000);
                $this->health->record($kind, $p['name'], $p['model'], 'invalid', 200, $ms, $e->usage, $e->getMessage());
                $attempts[] = ['provider' => $p['name'], 'outcome' => 'invalid', 'detail' => $e->getMessage()];
            }
        }

        throw new AllProvidersFailed($kind, $attempts);
    }

    private function guardDaily(string $kind): void
    {
        $limit = $this->registry->limit($kind);
        if ($limit > 0 && $this->health->todayCount($kind) >= $limit) {
            throw new DailyLimitReached(
                "Daily {$kind} call limit ({$limit}) reached. Raise it under Settings → Providers if that was unintended."
            );
        }
    }
}
