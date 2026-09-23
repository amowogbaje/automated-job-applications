<?php

namespace App\Services\JobSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdzunaSource implements JobSourceInterface
{
    public function name(): string
    {
        return 'adzuna';
    }

    public function fetch(): array
    {
        $appId = config('services.adzuna.app_id');
        $appKey = config('services.adzuna.app_key');
        $country = config('services.adzuna.country', 'gb'); // adzuna splits by country code

        // Adzuna's API needs some search term — unlike the other sources,
        // it doesn't return "everything" to filter afterward. This is a
        // deliberately broad, install-level fetch parameter (how wide a
        // net to cast from Adzuna specifically), not a per-user relevance
        // filter — that part happens per account now (see CareerProfile).
        $searchTerm = config('services.adzuna.search_term', 'software developer');

        if (! $appId || ! $appKey) {
            return []; // skip silently if not configured
        }

        try {
            $response = Http::timeout(15)->get("https://api.adzuna.com/v1/api/jobs/{$country}/search/1", [
                'app_id' => $appId,
                'app_key' => $appKey,
                'what' => $searchTerm,
                'sort_by' => 'date',
                'results_per_page' => 50,
            ]);

            if (! $response->successful()) {
                Log::warning('Adzuna fetch failed', ['status' => $response->status()]);
                return [];
            }

            $results = $response->json('results', []);

            return collect($results)->map(function ($job) {
                return [
                    'source' => $this->name(),
                    'external_id' => $job['id'] ?? null,
                    'title' => $job['title'] ?? 'Untitled',
                    'company' => $job['company']['display_name'] ?? null,
                    'location' => $job['location']['display_name'] ?? null,
                    'is_remote' => str_contains(strtolower($job['title'] ?? ''), 'remote'),
                    'description' => strip_tags($job['description'] ?? ''),
                    'url' => $job['redirect_url'] ?? '',
                    'posted_at' => isset($job['created']) ? Carbon::parse($job['created']) : null,
                ];
            })->filter(fn ($j) => filled($j['url']))->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Adzuna fetch exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
