<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Public Postings API — no key needed to READ. */
class LeverSource implements JobSourceInterface
{
    public function name(): string
    {
        return 'lever';
    }

    public function fetch(): array
    {
        $out = [];

        foreach (AtsBoardRegistry::boards()['lever'] ?? [] as $site) {
            try {
                $res = Http::timeout(20)->get("https://api.lever.co/v0/postings/{$site}", ['mode' => 'json']);
                if (! $res->successful()) {
                    Log::warning('Lever board fetch failed', ['site' => $site, 'status' => $res->status()]);
                    continue;
                }

                // Lever returns a bare array (not {"jobs": [...]}).
                foreach ((array) $res->json() as $job) {
                    if (! is_array($job)) {
                        continue;
                    }
                    $location = $job['categories']['location'] ?? null;

                    $out[] = [
                        'source' => $this->name(),
                        'external_id' => (string) ($job['id'] ?? ''),
                        'title' => $job['text'] ?? 'Untitled',
                        'company' => ucfirst($site),
                        'location' => $location,
                        'is_remote' => ($job['workplaceType'] ?? '') === 'remote' || ($location && stripos($location, 'remote') !== false),
                        'description' => trim(($job['descriptionPlain'] ?? '') . ' ' . ($job['additionalPlain'] ?? '')),
                        'url' => $job['hostedUrl'] ?? '',
                        'posted_at' => isset($job['createdAt']) ? Carbon::createFromTimestampMs((int) $job['createdAt']) : null,
                        'ats' => 'lever',
                        'ats_board' => $site,
                        'ats_job_id' => (string) ($job['id'] ?? ''),
                        'apply_url' => $job['applyUrl'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Lever board exception', ['site' => $site, 'error' => $e->getMessage()]);
            }
        }

        return array_values(array_filter($out, fn ($j) => filled($j['url'])));
    }
}
