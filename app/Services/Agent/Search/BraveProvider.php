<?php

namespace App\Services\Agent\Search;

class BraveProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->withHeaders(['X-Subscription-Token' => $this->key])
            ->get('https://api.search.brave.com/res/v1/web/search', array_filter(['q' => $query, 'count' => min($max, 20), 'freshness' => $days ? ($days <= 1 ? 'pd' : ($days <= 7 ? 'pw' : 'pm')) : null])));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''), 'snippet' => $this->clean($x['description'] ?? ''),
        ], (array) $r->json('web.results', []));
    }
}
