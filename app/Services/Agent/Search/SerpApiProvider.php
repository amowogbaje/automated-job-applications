<?php

namespace App\Services\Agent\Search;

class SerpApiProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->get('https://serpapi.com/search.json', array_filter([
            'engine' => 'google', 'q' => $query, 'num' => $max, 'api_key' => $this->key, 'tbs' => $this->qdr($days),
        ])));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['link'] ?? ''), 'snippet' => $this->clean($x['snippet'] ?? ''),
        ], array_slice((array) $r->json('organic_results', []), 0, $max));
    }
}
