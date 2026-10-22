<?php

namespace App\Services\Agent\Search;

class TavilyProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->withToken($this->key)->asJson()
            ->post('https://api.tavily.com/search', array_filter(['query' => $query, 'max_results' => $max, 'search_depth' => 'basic', 'time_range' => $days ? ($days <= 1 ? 'day' : ($days <= 7 ? 'week' : 'month')) : null])));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''), 'snippet' => $this->clean($x['content'] ?? ''),
        ], (array) $r->json('results', []));
    }
}
