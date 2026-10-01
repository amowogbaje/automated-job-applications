<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Public Job Board API — no key needed to READ. */
class GreenhouseSource implements JobSourceInterface
{
    public function name(): string
    {
        return 'greenhouse';
    }

    public function fetch(): array
    {
        $out = [];

        foreach (AtsBoardRegistry::boards()['greenhouse'] ?? [] as $board) {
            try {
                $res = Http::timeout(20)->get("https://boards-api.greenhouse.io/v1/boards/{$board}/jobs", ['content' => 'true']);
                if (! $res->successful()) {
                    Log::warning('Greenhouse board fetch failed', ['board' => $board, 'status' => $res->status()]);
                    continue;
                }

                foreach ($res->json('jobs', []) as $job) {
                    $location = $job['location']['name'] ?? null;
                    $html = html_entity_decode($job['content'] ?? '', ENT_QUOTES | ENT_HTML5);

                    $out[] = [
                        'source' => $this->name(),
                        'external_id' => (string) ($job['id'] ?? ''),
                        'title' => $job['title'] ?? 'Untitled',
                        'company' => $job['company_name'] ?? ucfirst($board),
                        'location' => $location,
                        'is_remote' => $location ? stripos($location, 'remote') !== false : false,
                        'description' => trim(preg_replace('/\s+/', ' ', strip_tags($html))),
                        'url' => $job['absolute_url'] ?? '',
                        'posted_at' => isset($job['updated_at']) ? Carbon::parse($job['updated_at']) : null,
                        'ats' => 'greenhouse',
                        'ats_board' => $board,
                        'ats_job_id' => (string) ($job['id'] ?? ''),
                        'apply_url' => $job['absolute_url'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Greenhouse board exception', ['board' => $board, 'error' => $e->getMessage()]);
            }
        }

        return array_values(array_filter($out, fn ($j) => filled($j['url'])));
    }
}
