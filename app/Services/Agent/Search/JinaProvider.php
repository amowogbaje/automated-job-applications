<?php

namespace App\Services\Agent\Search;

class JinaProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->withToken($this->key)
            ->withHeaders(['X-Respond-With' => 'no-content'])
            ->get('https://s.jina.ai/', ['q' => $query]));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''),
            'snippet' => $this->clean($x['description'] ?? ($x['content'] ?? '')),
        ], array_slice((array) $r->json('data', []), 0, $max));
    }
}
