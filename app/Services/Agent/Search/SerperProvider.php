<?php

namespace App\Services\Agent\Search;

class SerperProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->withHeaders(['X-API-KEY' => $this->key])->asJson()
            ->post('https://google.serper.dev/search', array_filter(['q' => $query, 'num' => $max, 'tbs' => $this->qdr($days)])));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['link'] ?? ''), 'snippet' => $this->clean($x['snippet'] ?? ''),
        ], (array) $r->json('organic', []));
    }
}
