<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Public posting API — no key needed to READ. */
class AshbySource implements JobSourceInterface
{
    public function name(): string
    {
        return 'ashby';
    }

    public function fetch(): array
    {
        $out = [];

        foreach (AtsBoardRegistry::boards()['ashby'] ?? [] as $board) {
            try {
                $res = Http::timeout(20)->get("https://api.ashbyhq.com/posting-api/job-board/{$board}");
                if (! $res->successful()) {
                    Log::warning('Ashby board fetch failed', ['board' => $board, 'status' => $res->status()]);
                    continue;
                }

                foreach ($res->json('jobs', []) as $job) {
                    $location = is_string($job['location'] ?? null) ? $job['location'] : ($job['location']['name'] ?? null);
                    $plain = $job['descriptionPlain'] ?? strip_tags($job['descriptionHtml'] ?? '');

                    $out[] = [
                        'source' => $this->name(),
                        'external_id' => (string) ($job['id'] ?? ''),
                        'title' => $job['title'] ?? 'Untitled',
                        'company' => ucfirst($board),
                        'location' => $location,
                        'is_remote' => (bool) ($job['isRemote'] ?? false) || ($location && stripos($location, 'remote') !== false),
                        'description' => trim(preg_replace('/\s+/', ' ', $plain)),
                        'url' => $job['jobUrl'] ?? '',
                        'posted_at' => isset($job['publishedAt']) ? Carbon::parse($job['publishedAt']) : null,
                        'ats' => 'ashby',
                        'ats_board' => $board,
                        'ats_job_id' => (string) ($job['id'] ?? ''),
                        'apply_url' => $job['applyUrl'] ?? ($job['jobUrl'] ?? null),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Ashby board exception', ['board' => $board, 'error' => $e->getMessage()]);
            }
        }

        return array_values(array_filter($out, fn ($j) => filled($j['url'])));
    }
}
