<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArbeitnowSource implements JobSourceInterface
{
    public function name(): string
    {
        return 'arbeitnow';
    }

    public function fetch(): array
    {
        try {
            $response = Http::timeout(15)->get('https://www.arbeitnow.com/api/job-board-api');

            if (! $response->successful()) {
                Log::warning('Arbeitnow fetch failed', ['status' => $response->status()]);
                return [];
            }

            $data = $response->json('data', []);

            return collect($data)->map(function ($job) {
                return [
                    'source' => $this->name(),
                    'external_id' => $job['slug'] ?? null,
                    'title' => $job['title'] ?? 'Untitled',
                    'company' => $job['company_name'] ?? null,
                    'location' => $job['location'] ?? null,
                    'is_remote' => (bool) ($job['remote'] ?? false),
                    'description' => strip_tags($job['description'] ?? ''),
                    'url' => $job['url'] ?? '',
                    'posted_at' => isset($job['created_at'])
                        ? Carbon::createFromTimestamp($job['created_at'])
                        : null,
                ];
            })->filter(fn ($j) => filled($j['url']))->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Arbeitnow fetch exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
