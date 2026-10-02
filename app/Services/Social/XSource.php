<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * X (Twitter) via the OFFICIAL API v2 recent search (last 7 days). No scraping.
 * X bills per post read (check your Developer Console for the current rate), so this
 * source only runs when X_BEARER_TOKEN is set and keeps to a hard daily read budget
 * (X_DAILY_READ_BUDGET posts/day) that is tracked locally.
 */
class XSource implements SocialSource
{
    private const HIRING_TERMS = ['hiring', '"we\'re hiring"', '"is hiring"', '"looking for"', '"join our team"', '"open role"', '"send your CV"'];

    public function name(): string
    {
        return 'x';
    }

    public function enabled(): bool
    {
        return filled(config('social.x.bearer_token'));
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        if ($skills === []) {
            return [];
        }

        if (! Cache::add('social.x.last_run', now()->timestamp, now()->addMinutes((int) config('social.x.min_interval_minutes', 240)))) {
            return []; // ran recently; spread the daily budget across the day
        }

        $posts = [];
        // Recent search only reaches back 7 days (and start_time must be at least ~10s in the past).
        $start = $since->copy()->max(now()->subDays(7)->addMinute());

        foreach (array_slice(self::buildQueries($skills, (string) config('social.x.language', 'en')), 0, (int) config('social.x.max_queries_per_run', 2)) as $query) {
            $max = max(10, min(100, (int) config('social.x.max_results', 25)));
            if ($this->remainingBudget() < $max) {
                Log::info('X daily read budget exhausted, skipping remaining queries');
                break;
            }

            try {
                $res = Http::withToken((string) config('social.x.bearer_token'))->timeout(20)->acceptJson()
                    ->get('https://api.x.com/2/tweets/search/recent', [
                        'query' => $query,
                        'max_results' => $max,
                        'start_time' => $start->utc()->format('Y-m-d\TH:i:s\Z'),
                        'tweet.fields' => 'created_at,author_id,lang,entities',
                        'expansions' => 'author_id',
                        'user.fields' => 'username,name',
                    ]);

                if (! $res->successful()) {
                    Log::warning('X recent search failed', ['status' => $res->status(), 'detail' => mb_substr($res->body(), 0, 200)]);
                    continue;
                }

                $tweets = (array) $res->json('data', []);
                $this->spend(count($tweets));

                $users = [];
                foreach ((array) $res->json('includes.users', []) as $u) {
                    $users[$u['id']] = $u['username'] ?? null;
                }

                foreach ($tweets as $t) {
                    $user = $users[$t['author_id'] ?? ''] ?? null;
                    $urls = array_values(array_filter(array_map(
                        fn ($u) => $u['unwound_url'] ?? $u['expanded_url'] ?? null,
                        (array) ($t['entities']['urls'] ?? [])
                    )));

                    $posts[] = [
                        'platform' => 'x',
                        'external_id' => (string) $t['id'],
                        'author' => $user ? '@' . $user : null,
                        'text' => html_entity_decode((string) $t['text'], ENT_QUOTES | ENT_HTML5),
                        'url' => 'https://x.com/' . ($user ?: 'i') . '/status/' . $t['id'],
                        'posted_at' => Carbon::parse($t['created_at'] ?? 'now'),
                        'urls' => $urls,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('X recent search exception', ['error' => $e->getMessage()]);
            }
        }

        return $posts;
    }

    /**
     * Skills are packed into as few queries as fit the 512-char self-serve limit:
     *   (hiring OR "looking for" ...) (laravel OR php OR ...) -is:retweet -is:reply lang:en
     *
     * @param  string[]  $skills
     * @return string[]
     */
    public static function buildQueries(array $skills, string $lang = 'en'): array
    {
        $intent = '(' . implode(' OR ', self::HIRING_TERMS) . ')';
        $suffix = ' -is:retweet -is:reply' . ($lang !== '' ? " lang:{$lang}" : '');

        $terms = [];
        foreach ($skills as $s) {
            // Multi-word skills are quoted; "C++"/"C#" style names can't be searched reliably, skip them.
            if (preg_match('/[+#]/', $s)) {
                continue;
            }
            $terms[] = str_contains($s, ' ') || str_contains($s, '.') ? '"' . $s . '"' : $s;
        }

        $queries = [];
        $group = [];
        foreach ($terms as $term) {
            $candidate = array_merge($group, [$term]);
            $q = $intent . ' (' . implode(' OR ', $candidate) . ')' . $suffix;
            if (strlen($q) > 480 && $group !== []) {
                $queries[] = $intent . ' (' . implode(' OR ', $group) . ')' . $suffix;
                $group = [$term];
            } else {
                $group = $candidate;
            }
        }
        if ($group !== []) {
            $queries[] = $intent . ' (' . implode(' OR ', $group) . ')' . $suffix;
        }

        return $queries;
    }

    private function key(): string
    {
        return 'social.x.reads.' . now()->format('Y-m-d');
    }

    private function remainingBudget(): int
    {
        return max(0, (int) config('social.x.daily_read_budget', 150) - (int) Cache::get($this->key(), 0));
    }

    private function spend(int $posts): void
    {
        Cache::add($this->key(), 0, now()->addDays(2));
        Cache::increment($this->key(), $posts);
    }
}
