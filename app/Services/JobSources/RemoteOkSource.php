<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemoteOkSource implements JobSourceInterface
{
    public function name(): string
    {
        return 'remoteok';
    }

    public function fetch(): array
    {
        try {
            // RemoteOK requires a normal User-Agent or it 403s.
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; JobAggregatorBot/1.0)',
            ])->timeout(15)->get('https://remoteok.com/api');

            if (! $response->successful()) {
                Log::warning('RemoteOK fetch failed', ['status' => $response->status()]);
                return [];
            }

            $data = $response->json();

            // First element is a metadata blob, skip it.
            return collect($data)->skip(1)->map(function ($job) {
                return [
                    'source' => $this->name(),
                    'external_id' => $job['id'] ?? null,
                    'title' => $job['position'] ?? $job['title'] ?? 'Untitled',
                    'company' => $job['company'] ?? null,
                    'location' => $job['location'] ?? 'Remote',
                    'is_remote' => true,
                    'description' => strip_tags($job['description'] ?? ''),
                    'url' => isset($job['id']) ? 'https://remoteok.com/remote-jobs/' . $job['id'] : ($job['url'] ?? ''),
                    'posted_at' => isset($job['date']) ? Carbon::parse($job['date']) : null,
                ];
            })->filter(fn ($j) => filled($j['url']))->values()->all();
        } catch (\Throwable $e) {
            Log::warning('RemoteOK fetch exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
