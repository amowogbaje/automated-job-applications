<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Ask HN: Who is hiring?" monthly threads via the free HN Algolia API (no key).
 * Every top-level comment is a job post, and many say "email us". Because the
 * thread is monthly, a single run sees the whole current month; the lookback
 * filter keeps only comments newer than $since.
 */
class HackerNewsSource implements SocialSource
{
    public function name(): string
    {
        return 'hackernews';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        $posts = [];

        try {
            $threads = Http::timeout(20)->get('https://hn.algolia.com/api/v1/search_by_date', [
                'query' => 'Ask HN: Who is hiring?',
                'tags' => 'story,author_whoishiring',
                'hitsPerPage' => 6,
            ])->json('hits', []);

            $threads = array_values(array_filter($threads, fn ($t) => stripos($t['title'] ?? '', 'who is hiring') !== false));
            $threads = array_slice($threads, 0, (int) config('social.hackernews.max_threads', 1));

            foreach ($threads as $thread) {
                $storyId = (string) $thread['objectID'];

                for ($page = 0; $page < (int) config('social.hackernews.max_pages', 3); $page++) {
                    $res = Http::timeout(20)->get('https://hn.algolia.com/api/v1/search_by_date', [
                        'tags' => "comment,story_{$storyId}",
                        'numericFilters' => 'created_at_i>' . $since->getTimestamp(),
                        'hitsPerPage' => 100,
                        'page' => $page,
                    ]);
                    if (! $res->successful()) {
                        break;
                    }

                    $hits = $res->json('hits', []);
                    foreach ($hits as $c) {
                        // Top-level comments only (replies are chatter, not postings).
                        if ((string) ($c['parent_id'] ?? '') !== $storyId || empty($c['comment_text'])) {
                            continue;
                        }

                        $text = self::htmlToText($c['comment_text']);
                        $posts[] = [
                            'platform' => 'hackernews',
                            'external_id' => (string) $c['objectID'],
                            'author' => $c['author'] ?? null,
                            'text' => $text,
                            'url' => 'https://news.ycombinator.com/item?id=' . $c['objectID'],
                            'posted_at' => Carbon::createFromTimestamp((int) $c['created_at_i']),
                            'urls' => self::hrefs($c['comment_text']),
                        ];
                    }

                    if (count($hits) < 100) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('HN who-is-hiring fetch failed', ['error' => $e->getMessage()]);
        }

        return array_merge($posts, $this->jobStories($since));
    }

    /**
     * The official HN "jobs" feed (companies, mostly YC startups, post openings here).
     * Free Firebase API, no key. Items are a title ("X (YC S21) is hiring a Laravel engineer")
     * plus an optional description and link.
     */
    private function jobStories(CarbonInterface $since): array
    {
        $out = [];

        try {
            $ids = array_slice((array) Http::timeout(15)->get('https://hacker-news.firebaseio.com/v0/jobstories.json')->json(), 0, 40);

            foreach ($ids as $id) {
                $item = Http::timeout(10)->get("https://hacker-news.firebaseio.com/v0/item/{$id}.json")->json();
                if (! is_array($item) || ($item['type'] ?? '') !== 'job' || ! empty($item['dead']) || ! empty($item['deleted'])) {
                    continue;
                }

                $posted = Carbon::createFromTimestamp((int) ($item['time'] ?? 0));
                if ($posted->lt($since)) {
                    continue; // the feed is newest-first, but keep scanning: ordering isn't guaranteed
                }

                $body = isset($item['text']) ? self::htmlToText($item['text']) : '';
                $link = $item['url'] ?? null;
                $out[] = [
                    'platform' => 'hackernews',
                    'external_id' => 'job-' . $id,
                    'author' => $item['by'] ?? null,
                    'text' => trim(($item['title'] ?? '') . ($body !== '' ? "\n\n" . $body : '') . ($link ? "\n\n" . $link : '')),
                    'url' => 'https://news.ycombinator.com/item?id=' . $id,
                    'posted_at' => $posted,
                    'urls' => array_values(array_filter([$link])),
                ];
            }
        } catch (\Throwable $e) {
            Log::info('HN jobstories fetch failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(?:p|br)\s*/?>#i', "\n", $html);

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    /** @return string[] */
    public static function hrefs(string $html): array
    {
        preg_match_all('/href="([^"]+)"/i', $html, $m);

        return array_map(fn ($u) => html_entity_decode($u, ENT_QUOTES | ENT_HTML5), $m[1]);
    }
}
