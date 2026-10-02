<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reddit via the OFFICIAL Data API (OAuth, app-only token). Free for eligible NON-COMMERCIAL use at
 * 100 queries/minute, but new apps must be registered at reddit.com/prefs/apps and may need Reddit's
 * approval first (see integration.md). Without credentials this source stays off; unauthenticated
 * requests are blocked by Reddit, so there's no keyless fallback.
 */
class RedditSource implements SocialSource
{
    public function name(): string
    {
        return 'reddit';
    }

    public function enabled(): bool
    {
        return filled(config('social.reddit.client_id')) && filled(config('social.reddit.client_secret'));
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        $token = $this->token();
        if (! $token) {
            return [];
        }

        $posts = [];
        foreach ((array) config('social.reddit.subreddits', []) as $sub) {
            try {
                $res = Http::withToken($token)->withHeaders(['User-Agent' => $this->userAgent()])->timeout(20)->acceptJson()
                    ->get("https://oauth.reddit.com/r/{$sub}/new", ['limit' => 100, 'raw_json' => 1]);

                if (! $res->successful()) {
                    Log::info('Reddit fetch failed', ['sub' => $sub, 'status' => $res->status()]);
                    continue;
                }

                foreach ((array) $res->json('data.children', []) as $child) {
                    $d = (array) ($child['data'] ?? []);
                    $created = Carbon::createFromTimestamp((int) ($d['created_utc'] ?? 0));
                    if ($created->lt($since) || ! empty($d['over_18']) || ! empty($d['stickied'])) {
                        continue;
                    }

                    $title = (string) ($d['title'] ?? '');
                    if (! self::acceptTitle($title, $sub)) {
                        continue;
                    }

                    $body = (string) ($d['selftext'] ?? '');
                    $posts[] = [
                        'platform' => 'reddit',
                        'external_id' => (string) ($d['name'] ?? $d['id'] ?? ''),
                        'author' => isset($d['author']) ? 'u/' . $d['author'] : null,
                        'text' => trim($title . ($body !== '' ? "\n\n" . $body : '')),
                        'url' => 'https://www.reddit.com' . ($d['permalink'] ?? ''),
                        'posted_at' => $created,
                        'urls' => self::links($body . ' ' . ($d['url'] ?? '')),
                    ];
                }
            } catch (\Throwable $e) {
                Log::info('Reddit fetch exception', ['sub' => $sub, 'error' => $e->getMessage()]);
            }
        }

        return $posts;
    }

    /**
     * r/forhire uses [Hiring] / [For Hire] / [Task] tags in titles. We want only [Hiring].
     * Other subreddits fall through to the generic hiring-wording filter in the pipeline.
     */
    public static function acceptTitle(string $title, string $sub): bool
    {
        if (preg_match('/\[\s*(for hire|task|offer)\s*\]/i', $title)) {
            return false;
        }
        if (strtolower($sub) === 'forhire') {
            return (bool) preg_match('/\[\s*hiring\s*\]/i', $title);
        }

        return true;
    }

    /** @return string[] */
    public static function links(string $text): array
    {
        preg_match_all('#https?://[^\s)\]>"]+#i', $text, $m);

        return array_values(array_unique(array_map(fn ($u) => rtrim($u, '.,;'), $m[0])));
    }

    private function userAgent(): string
    {
        return (string) config('social.reddit.user_agent');
    }

    private function token(): ?string
    {
        if ($t = Cache::get('social.reddit.token')) {
            return $t;
        }

        try {
            $res = Http::asForm()->withBasicAuth((string) config('social.reddit.client_id'), (string) config('social.reddit.client_secret'))
                ->withHeaders(['User-Agent' => $this->userAgent()])->timeout(20)->acceptJson()
                ->post('https://www.reddit.com/api/v1/access_token', ['grant_type' => 'client_credentials']);

            if (! $res->successful() || ! $res->json('access_token')) {
                Log::warning('Reddit token request failed (is the app registered/approved?)', ['status' => $res->status()]);

                return null;
            }

            Cache::put('social.reddit.token', $res->json('access_token'), now()->addSeconds(max(300, (int) $res->json('expires_in', 3600) - 300)));

            return $res->json('access_token');
        } catch (\Throwable $e) {
            Log::warning('Reddit token exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
