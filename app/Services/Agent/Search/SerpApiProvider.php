<?php

namespace App\Services\Agent\Search;

class SerpApiProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8): array
    {
        $r = $this->send(fn () => $this->client()->get('https://serpapi.com/search.json', [
            'engine' => 'google', 'q' => $query, 'num' => $max, 'api_key' => $this->key,
        ]));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['link'] ?? ''), 'snippet' => $this->clean($x['snippet'] ?? ''),
        ], array_slice((array) $r->json('organic_results', []), 0, $max));
    }
}
