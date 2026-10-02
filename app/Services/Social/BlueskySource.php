<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bluesky: free. Post search currently requires a logged-in session, so create a free
 * Bluesky account, make an APP PASSWORD (Settings > Privacy and Security > App Passwords;
 * never your main password) and put the handle + app password in .env. Official AT Protocol
 * API, read-only. The session token is cached so we don't log in on every run.
 */
class BlueskySource implements SocialSource
{
    private const HOST = 'https://bsky.social';

    public function name(): string
    {
        return 'bluesky';
    }

    public function enabled(): bool
    {
        return filled(config('social.bluesky.identifier')) && filled(config('social.bluesky.app_password'));
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        $jwt = $this->session();
        if (! $jwt) {
            return [];
        }

        $posts = [];
        $seen = [];

        foreach (self::buildQueries($skills, (int) config('social.bluesky.max_queries_per_run', 6)) as $q) {
            try {
                $res = $this->search($jwt, $q, $since);
                if ($res->status() === 401) { // expired token: log in again once
                    Cache::forget('social.bluesky.jwt');
                    $jwt = $this->session();
                    $res = $jwt ? $this->search($jwt, $q, $since) : $res;
                }
                if (! $res->successful()) {
                    Log::info('Bluesky search failed', ['status' => $res->status(), 'q' => $q]);
                    continue;
                }

                foreach ((array) $res->json('posts', []) as $p) {
                    $uri = $p['uri'] ?? null;
                    if (! $uri || isset($seen[$uri])) {
                        continue;
                    }
                    $seen[$uri] = true;

                    $handle = $p['author']['handle'] ?? null;
                    $record = (array) ($p['record'] ?? []);
                    $created = Carbon::parse($record['createdAt'] ?? $p['indexedAt'] ?? 'now');
                    if ($created->lt($since)) {
                        continue;
                    }

                    $posts[] = [
                        'platform' => 'bluesky',
                        'external_id' => $uri,
                        'author' => $handle ? '@' . $handle : null,
                        'text' => (string) ($record['text'] ?? ''),
                        'url' => self::postUrl($uri, $handle),
                        'posted_at' => $created,
                        'urls' => self::facetLinks($record),
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('Bluesky search exception', ['error' => $e->getMessage()]);
            }
        }

        return $posts;
    }

    private function search(string $jwt, string $q, CarbonInterface $since)
    {
        return Http::withToken($jwt)->timeout(20)->acceptJson()->get(self::HOST . '/xrpc/app.bsky.feed.searchPosts', [
            'q' => $q,
            'sort' => 'latest',
            'since' => $since->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'limit' => (int) config('social.bluesky.per_query', 40),
        ]);
    }

    private function session(): ?string
    {
        if ($jwt = Cache::get('social.bluesky.jwt')) {
            return $jwt;
        }

        try {
            $res = Http::timeout(20)->acceptJson()->asJson()->post(self::HOST . '/xrpc/com.atproto.server.createSession', [
                'identifier' => config('social.bluesky.identifier'),
                'password' => config('social.bluesky.app_password'),
            ]);
            if (! $res->successful() || ! $res->json('accessJwt')) {
                Log::warning('Bluesky login failed (check handle + APP password)', ['status' => $res->status()]);

                return null;
            }

            // Access tokens last a couple of hours; refresh well before that.
            Cache::put('social.bluesky.jwt', $res->json('accessJwt'), now()->addMinutes(75));

            return $res->json('accessJwt');
        } catch (\Throwable $e) {
            Log::warning('Bluesky login exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * One query per skill: `hiring laravel`. (Bluesky search treats words as AND.) Skills that
     * can't be searched as plain words (C++, C#) are skipped.
     *
     * @param  string[]  $skills
     * @return string[]
     */
    public static function buildQueries(array $skills, int $max = 6): array
    {
        $out = [];
        foreach ($skills as $s) {
            if (preg_match('/[+#]/', $s)) {
                continue;
            }
            $out[] = 'hiring ' . (str_contains($s, ' ') ? '"' . $s . '"' : $s);
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** at://did:plc:abc/app.bsky.feed.post/3kxyz  ->  https://bsky.app/profile/{handle}/post/3kxyz */
    public static function postUrl(string $uri, ?string $handle): string
    {
        $rkey = substr(strrchr($uri, '/') ?: '/', 1);
        $who = $handle ?: (preg_match('#^at://([^/]+)/#', $uri, $m) ? $m[1] : 'unknown');

        return "https://bsky.app/profile/{$who}/post/{$rkey}";
    }

    /** @return string[] real link targets (post text only shows a shortened version) */
    public static function facetLinks(array $record): array
    {
        $links = [];
        foreach ((array) ($record['facets'] ?? []) as $facet) {
            foreach ((array) ($facet['features'] ?? []) as $f) {
                if (($f['$type'] ?? '') === 'app.bsky.richtext.facet#link' && ! empty($f['uri'])) {
                    $links[] = $f['uri'];
                }
            }
        }

        return array_values(array_unique($links));
    }
}
