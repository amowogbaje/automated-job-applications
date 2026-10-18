<?php

namespace App\Services\Agent\Search;

/** Self-hosted SearXNG. The "key" is the instance base URL, e.g. https://search.example.com (JSON format must be enabled). */
class SearxngProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8): array
    {
        $base = rtrim($this->key, '/');
        $r = $this->send(fn () => $this->client()->get($base.'/search', ['q' => $query, 'format' => 'json']));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''), 'snippet' => $this->clean($x['content'] ?? ''),
        ], array_slice((array) $r->json('results', []), 0, $max));
    }
}
