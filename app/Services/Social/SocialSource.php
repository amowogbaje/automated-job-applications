<?php

namespace App\Services\Social;

use Carbon\CarbonInterface;

interface SocialSource
{
    /** Stored as job_listings.source / social_platform: x | mastodon | hackernews */
    public function name(): string;

    /** Configured and allowed to run (e.g. X needs a token)? */
    public function enabled(): bool;

    /**
     * @param  string[]  $skills  canonical core skill names across all accounts, most common first
     * @return array<int, array{platform:string, external_id:string, author:?string, text:string, url:string, posted_at:CarbonInterface, urls?:string[]}>
     */
    public function fetch(array $skills, CarbonInterface $since): array;
}
