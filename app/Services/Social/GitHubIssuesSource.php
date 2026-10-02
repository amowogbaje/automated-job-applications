<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GitHub has NO jobs feed any more (GitHub Jobs closed in 2021), so this only watches repositories YOU
 * name where people post openings as issues (GITHUB_JOB_REPOS=owner/repo,owner/repo). Official REST API;
 * works without a token at 60 requests/hour, a free personal token raises that to 5,000/hour.
 * Off until at least one repo is configured.
 */
class GitHubIssuesSource implements SocialSource
{
    public function name(): string
    {
        return 'github';
    }

    public function enabled(): bool
    {
        return config('social.github.repos', []) !== [];
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'job-feed/1.0'];
        if (filled(config('social.github.token'))) {
            $headers['Authorization'] = 'Bearer ' . config('social.github.token');
        }

        $posts = [];
        foreach ((array) config('social.github.repos', []) as $repo) {
            try {
                $res = Http::withHeaders($headers)->timeout(20)->get("https://api.github.com/repos/{$repo}/issues", [
                    'state' => 'open', 'sort' => 'created', 'direction' => 'desc', 'per_page' => 50,
                    'since' => $since->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                ]);
                if (! $res->successful()) {
                    Log::info('GitHub issues fetch failed', ['repo' => $repo, 'status' => $res->status()]);
                    continue;
                }

                foreach ((array) $res->json() as $i) {
                    if (isset($i['pull_request']) || empty($i['html_url'])) {
                        continue; // pull requests aren't postings
                    }
                    $created = Carbon::parse($i['created_at'] ?? 'now');
                    if ($created->lt($since)) {
                        continue;
                    }
                    $body = (string) ($i['body'] ?? '');
                    $posts[] = [
                        'platform' => 'github',
                        'external_id' => (string) $i['id'],
                        'author' => isset($i['user']['login']) ? '@' . $i['user']['login'] . " ({$repo})" : $repo,
                        'text' => trim(($i['title'] ?? '') . ($body !== '' ? "\n\n" . $body : '')),
                        'url' => $i['html_url'],
                        'posted_at' => $created,
                        'urls' => RedditSource::links($body),
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('GitHub issues exception', ['repo' => $repo, 'error' => $e->getMessage()]);
            }
        }

        return $posts;
    }
}
