<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lemmy (federated Reddit alternative). Every instance exposes the same public, unauthenticated
 * /api/v3 REST API, so this is free and needs no account. Two modes, both optional:
 *   - search each instance for "hiring {skill}" (newest first), and
 *   - read specific communities you list (LEMMY_COMMUNITIES=jobs@lemmy.world,...).
 */
class LemmySource implements SocialSource
{
    public function name(): string
    {
        return 'lemmy';
    }

    public function enabled(): bool
    {
        return config('social.lemmy.instances', []) !== [];
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        $posts = [];
        $seen = [];

        foreach ((array) config('social.lemmy.instances', []) as $instance) {
            $requests = [];
            foreach (array_slice($skills, 0, (int) config('social.lemmy.max_skill_queries', 5)) as $skill) {
                if (! preg_match('/[+#]/', $skill)) {
                    $requests[] = ['/api/v3/search', ['q' => 'hiring ' . $skill, 'type_' => 'Posts', 'sort' => 'New', 'limit' => 30]];
                }
            }
            foreach ((array) config('social.lemmy.communities', []) as $community) {
                $requests[] = ['/api/v3/post/list', ['community_name' => $community, 'sort' => 'New', 'limit' => 40]];
            }

            foreach ($requests as [$path, $params]) {
                try {
                    $res = Http::timeout(20)->acceptJson()->withHeaders(['User-Agent' => 'job-feed/1.0'])->get("https://{$instance}{$path}", $params);
                    if (! $res->successful()) {
                        continue; // some instances disable search or rate-limit; skip quietly
                    }

                    foreach ((array) $res->json('posts', []) as $row) {
                        $p = (array) ($row['post'] ?? []);
                        $apId = $p['ap_id'] ?? null;
                        if (! $apId || isset($seen[$apId]) || ! empty($p['deleted']) || ! empty($p['removed']) || ! empty($p['nsfw'])) {
                            continue;
                        }
                        $published = Carbon::parse($p['published'] ?? 'now');
                        if ($published->lt($since)) {
                            continue;
                        }
                        $seen[$apId] = true;

                        $title = (string) ($p['name'] ?? '');
                        $body = (string) ($p['body'] ?? '');
                        $community = $row['community']['name'] ?? null;

                        $posts[] = [
                            'platform' => 'lemmy',
                            'external_id' => $apId,
                            'author' => isset($row['creator']['name']) ? $row['creator']['name'] . ($community ? " in !{$community}" : '') : null,
                            'text' => trim($title . ($body !== '' ? "\n\n" . $body : '')),
                            'url' => $apId,
                            'posted_at' => $published,
                            'urls' => array_values(array_filter(array_merge([$p['url'] ?? null], RedditSource::links($body)))),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::info('Lemmy fetch failed', ['instance' => $instance, 'path' => $path, 'error' => $e->getMessage()]);
                }
            }
        }

        return $posts;
    }
}
