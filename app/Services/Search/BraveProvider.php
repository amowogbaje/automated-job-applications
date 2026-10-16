<?php

namespace App\Services\Search;

class BraveProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8): array
    {
        $r = $this->send(fn () => $this->client()->withHeaders(['X-Subscription-Token' => $this->key])
            ->get('https://api.search.brave.com/res/v1/web/search', ['q' => $query, 'count' => min($max, 20)]));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''), 'snippet' => $this->clean($x['description'] ?? ''),
        ], (array) $r->json('web.results', []));
    }
}
