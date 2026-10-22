<?php

namespace App\Services\Agent\Search;

class FirecrawlProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->withToken($this->key)->asJson()
            ->post('https://api.firecrawl.dev/v1/search', array_filter(['query' => $query, 'limit' => $max, 'tbs' => $this->qdr($days)])));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''), 'snippet' => $this->clean($x['description'] ?? ''),
        ], (array) $r->json('data', []));
    }
}
