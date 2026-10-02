<?php

namespace App\Services\Social;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Public hashtag timelines on Mastodon instances (no key). We read general hiring
 * hashtags (#hiring, #fedihire...) AND one tag per core skill (#laravel, #php...),
 * then the pipeline keeps only posts with hiring wording that match an account's skills.
 */
class MastodonSource implements SocialSource
{
    public function name(): string
    {
        return 'mastodon';
    }

    public function enabled(): bool
    {
        return config('social.mastodon.instances', []) !== [];
    }

    public function fetch(array $skills, CarbonInterface $since): array
    {
        $tags = array_map('strtolower', (array) config('social.mastodon.hashtags', []));
        foreach (array_slice($skills, 0, 8) as $skill) {
            $tag = SkillTags::hashtag($skill);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique($tags));

        $posts = [];
        $seen = [];

        foreach ((array) config('social.mastodon.instances', []) as $instance) {
            foreach ($tags as $tag) {
                try {
                    $res = Http::timeout(15)->acceptJson()->get("https://{$instance}/api/v1/timelines/tag/{$tag}", [
                        'limit' => (int) config('social.mastodon.per_tag', 40),
                    ]);
                    if (! $res->successful()) {
                        continue; // some instances require login for tag timelines; skip quietly
                    }

                    foreach ((array) $res->json() as $s) {
                        $created = Carbon::parse($s['created_at'] ?? 'now');
                        $uri = $s['url'] ?? ($s['uri'] ?? null);
                        if (! $uri || $created->lt($since) || isset($seen[$uri]) || ! empty($s['reblog']) || ! empty($s['in_reply_to_id'])) {
                            continue;
                        }
                        $seen[$uri] = true;

                        $html = (string) ($s['content'] ?? '');
                        $posts[] = [
                            'platform' => 'mastodon',
                            'external_id' => (string) ($s['id'] ?? $uri) . '@' . $instance,
                            'author' => isset($s['account']['acct']) ? '@' . $s['account']['acct'] : null,
                            'text' => HackerNewsSource::htmlToText($html),
                            'url' => $uri,
                            'posted_at' => $created,
                            'urls' => HackerNewsSource::hrefs($html),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::info('Mastodon tag fetch failed', ['instance' => $instance, 'tag' => $tag, 'error' => $e->getMessage()]);
                }
            }
        }

        return $posts;
    }
}
