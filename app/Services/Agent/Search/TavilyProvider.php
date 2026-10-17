<?php

namespace App\Services\Agent\Search;

class TavilyProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8): array
    {
        $r = $this->send(fn () => $this->client()->withToken($this->key)->asJson()
            ->post('https://api.tavily.com/search', ['query' => $query, 'max_results' => $max, 'search_depth' => 'basic']));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''), 'snippet' => $this->clean($x['content'] ?? ''),
        ], (array) $r->json('results', []));
    }
}
