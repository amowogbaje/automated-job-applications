<?php

namespace App\Services\JobSources;

interface JobSourceInterface
{
    /**
     * Fetch raw listings and return them normalized as arrays:
     * [
     *   'source' => 'arbeitnow',
     *   'external_id' => '...',
     *   'title' => '...',
     *   'company' => '...',
     *   'location' => '...',
     *   'is_remote' => true,
     *   'description' => '...',
     *   'url' => '...',
     *   'posted_at' => Carbon|null,
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetch(): array;

    public function name(): string;
}
