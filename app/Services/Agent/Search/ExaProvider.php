<?php

namespace App\Services\Agent\Search;

class ExaProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8, ?int $days = null): array
    {
        $r = $this->send(fn () => $this->client()->withHeaders(['x-api-key' => $this->key])->asJson()
            ->post('https://api.exa.ai/search', [
                'query' => $query, 'numResults' => $max, 'contents' => ['text' => ['maxCharacters' => 500]],
            ] + ($days ? ['startPublishedDate' => now()->subDays($days)->toIso8601String()] : [])));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''),
            'snippet' => $this->clean($x['text'] ?? ($x['summary'] ?? '')), 'date' => $x['publishedDate'] ?? null,
        ], (array) $r->json('results', []));
    }
}
