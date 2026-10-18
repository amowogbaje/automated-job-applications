<?php

namespace App\Services\Agent\Search;

class ExaProvider extends HttpSearchProvider
{
    public function search(string $query, int $max = 8): array
    {
        $r = $this->send(fn () => $this->client()->withHeaders(['x-api-key' => $this->key])->asJson()
            ->post('https://api.exa.ai/search', [
                'query' => $query, 'numResults' => $max, 'contents' => ['text' => ['maxCharacters' => 500]],
            ]));

        return array_map(fn ($x) => [
            'title' => $this->clean($x['title'] ?? '', 200), 'url' => (string) ($x['url'] ?? ''),
            'snippet' => $this->clean($x['text'] ?? ($x['summary'] ?? '')),
        ], (array) $r->json('results', []));
    }
}
