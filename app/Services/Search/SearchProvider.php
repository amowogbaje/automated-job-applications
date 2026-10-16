<?php

namespace App\Services\Search;

interface SearchProvider
{
    /** @return array<int, array{title: string, url: string, snippet: string}> */
    public function search(string $query, int $max = 8): array;
}
