<?php

namespace App\Services\Search;

use App\Services\Providers\Failover;
use App\Services\Providers\ProviderException;
use App\Services\Providers\ProviderHealth;
use App\Services\Providers\ProviderRegistry;

/** Web search with automatic fallback. Order, on/off, cooldowns and daily limit come from the Providers page. */
class SearchManager
{
    public function __construct(private Failover $failover, private ProviderRegistry $registry, private ProviderHealth $health) {}

    /** Names that could be used right now: enabled, key present, not resting. */
    public function activeNames(): array
    {
        return collect($this->registry->all('search'))
            ->filter(fn ($p) => $p['enabled'] && $p['configured'])->pluck('name')->values()->all();
    }

    /** @return array{provider: string, results: array, attempts: array} */
    public function search(string $query, int $max = 6): array
    {
        $r = $this->failover->run('search', fn (array $p) => ['results' => $this->driver($p)->search($query, $max), 'usage' => null]);

        $seen = [];
        $results = [];
        foreach ($r['out']['results'] as $x) {
            $url = trim($x['url']);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $results[] = ['title' => $x['title'], 'url' => $url, 'snippet' => $x['snippet']];
        }

        return ['provider' => $r['provider'], 'results' => $results, 'attempts' => $r['attempts']];
    }

    /** Direct test of one provider (ignores order and cooldown); logged as a test call. */
    public function test(string $name): array
    {
        $p = $this->registry->find('search', $name);
        if (! $p || ! $p['configured']) {
            return ['ok' => false, 'message' => 'No API key found in .env.'];
        }
        $start = microtime(true);
        try {
            $n = count($this->driver($p)->search('laravel framework', 3));
            $ms = (int) ((microtime(true) - $start) * 1000);
            $this->health->record('search', $name, null, 'ok', 200, $ms, null, null, true);
            $this->health->clear('search', $name);

            return ['ok' => true, 'message' => "Works: {$n} result(s) in {$ms} ms."];
        } catch (ProviderException $e) {
            $this->health->record('search', $name, null, $e->kind, $e->status, null, null, $e->getMessage(), true);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function driver(array $p): SearchProvider
    {
        return match ($p['driver']) {
            'tavily' => new TavilyProvider((string) $p['key']),
            'serper' => new SerperProvider((string) $p['key']),
            'brave' => new BraveProvider((string) $p['key']),
            default => throw new ProviderException(ProviderException::REJECTED, "Unknown search driver {$p['driver']}."),
        };
    }
}
