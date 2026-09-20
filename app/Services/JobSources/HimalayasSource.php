<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HimalayasSource implements JobSourceInterface
{
    public function name(): string
    {
        return 'himalayas';
    }

    public function fetch(): array
    {
        try {
            $response = Http::timeout(15)->get('https://himalayas.app/jobs/api', [
                'limit' => 50,
            ]);

            if (! $response->successful()) {
                Log::warning('Himalayas fetch failed', ['status' => $response->status()]);
                return [];
            }

            $jobs = $response->json('jobs', []);

            return collect($jobs)->map(function ($job) {
                return [
                    'source' => $this->name(),
                    'external_id' => $job['guid'] ?? $job['id'] ?? null,
                    'title' => $job['title'] ?? 'Untitled',
                    'company' => $job['companyName'] ?? null,
                    'location' => $job['locationRestrictions'][0] ?? 'Remote',
                    'is_remote' => true,
                    'description' => strip_tags($job['description'] ?? ''),
                    'url' => $job['applicationLink'] ?? $job['url'] ?? '',
                    'posted_at' => isset($job['pubDate'])
                        ? Carbon::createFromTimestamp($job['pubDate'])
                        : null,
                ];
            })->filter(fn ($j) => filled($j['url']))->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Himalayas fetch exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
