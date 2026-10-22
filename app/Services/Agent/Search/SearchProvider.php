<?php

namespace App\Services\Agent\Search;

interface SearchProvider
{
    /** @return array<int, array{title: string, url: string, snippet: string}> */
    /** $days limits results to roughly the last N days where the provider supports it. */
    public function search(string $query, int $max = 8, ?int $days = null): array;
}
