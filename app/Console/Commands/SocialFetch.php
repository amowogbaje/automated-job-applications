<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Services\Dedupe\JobDeduper;
use App\Services\Social\BlueskySource;
use App\Services\Social\GitHubIssuesSource;
use App\Services\Social\LemmySource;
use App\Services\Social\RedditSource;
use App\Services\Social\HackerNewsSource;
use App\Services\Social\HiringIntent;
use App\Services\Social\MastodonSource;
use App\Services\Social\SkillTags;
use App\Services\Social\SocialPostExtractor;
use App\Services\Social\SocialSource;
use App\Services\Social\XSource;
use App\Services\Translation\LanguageDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SocialFetch extends Command
{
    protected $signature = 'social:fetch {--source= : Only this source (hackernews|mastodon|bluesky|lemmy|reddit|github|x)} {--no-ai : Skip AI extraction (stores link-only posts; useful for testing sources)} {--limit-ai= : Max AI extractions this run}';

    protected $description = 'Find recent job posts on X / Mastodon / Hacker News that match the skills of accounts on this install; the AI pulls out how to apply (email, subject, link). Posts that don\'t match anyone are dropped before any AI is spent.';

    public function handle(SocialPostExtractor $extractor, LanguageDetector $detector, JobDeduper $deduper): int
    {
        $profiles = CareerProfile::query()->where('social_jobs_enabled', true)->get();
        if ($profiles->isEmpty()) {
            $this->info('No account has social jobs enabled, nothing to do.');

            return self::SUCCESS;
        }

        $skills = SkillTags::coreSkills();
        if ($skills === []) {
            $this->warn('No core skills on any profile yet, so there is nothing to search for. Import a resume / set skills first.');

            return self::SUCCESS;
        }
        $this->info('Searching for: ' . implode(', ', $skills));

        $since = now()->subHours((int) config('social.lookback_hours', 48));
        $aiBudget = (int) ($this->option('limit-ai') ?: config('social.ai_per_run', 20));

        $sources = array_filter([new HackerNewsSource(), new MastodonSource(), new BlueskySource(), new LemmySource(), new RedditSource(), new GitHubIssuesSource(), new XSource()], function (SocialSource $s) {
            return in_array($s->name(), config('social.sources', []), true)
                && (! $this->option('source') || $this->option('source') === $s->name())
                && $s->enabled();
        });

        $seen = $kept = $stored = $merged = 0;

        foreach ($sources as $source) {
            $this->info("Fetching from {$source->name()}...");
            $posts = $source->fetch($skills, $since);
            $seen += count($posts);
            $this->line('  ' . count($posts) . ' post(s) returned');

            foreach ($posts as $post) {
                $hash = sha1('social:' . $post['url']);
                if (JobListing::where('url_hash', $hash)->exists()) {
                    continue; // already stored (also: never pay the AI twice for the same post)
                }

                // 1) free filters first
                if (! HiringIntent::looksLikeJobPost($post['text'])) {
                    continue;
                }
                $relevant = $profiles->contains(fn (CareerProfile $p) => $p->score(Str::limit($post['text'], 100, ''), $post['text'])['matches']);
                if (! $relevant) {
                    continue;
                }

                // 1b) Cross-post check BEFORE spending any AI: if this post's wording (or its apply email)
                // is already stored, just record "also posted on {platform}" on the existing listing.
                if ($existing = $deduper->findByText($post['text'])) {
                    $deduper->noteCrossPost($existing, $source->name(), $post['url'], $post['posted_at'], $source->name());
                    $merged++;
                    $this->line("  = already have it (#{$existing->id}), noted it was also on {$source->name()}");
                    continue;
                }
                $kept++;

                // 2) AI extraction, capped per run
                $x = null;
                if (! $this->option('no-ai') && $aiBudget > 0) {
                    $aiBudget--;
                    $x = $extractor->extract($post['text'], $post['urls'] ?? []);
                    if ($x !== null && ! $x['is_job']) {
                        $this->line("  skipped (AI: not a job post) {$post['url']}");
                        continue;
                    }
                }

                $emailApply = $x && $x['apply_email'] && ! $x['needs_manual_input'];
                $language = $detector->detect($post['text']);

                $outcome = $deduper->ingest([
                    'kind' => 'social',
                    'source' => $source->name(),
                    'external_id' => $post['external_id'],
                    'title' => $x['title'] ?? Str::limit(trim(strtok($post['text'], "\n")), 90),
                    'company' => $x['company'] ?? $post['author'],
                    'location' => $x['location'] ?? null,
                    'is_remote' => (bool) ($x['remote'] ?? preg_match('/\bremote\b/i', $post['text'])),
                    'description' => Str::limit($post['text'], 8000, ''),
                    'language' => $language,
                    'translation_status' => $language === 'en' ? 'none' : 'pending',
                    'url' => $post['url'],
                    'url_hash' => $hash,
                    'apply_method' => $emailApply ? 'email' : 'web',
                    'apply_email' => $emailApply ? $x['apply_email'] : null,
                    'apply_url' => $x['apply_url'] ?? $post['url'],
                    'social_platform' => $source->name(),
                    'social_author' => $post['author'],
                    'apply_instructions' => $x['instructions'] ?? null,
                    'apply_subject' => $x['subject'] ?? null,
                    'needs_manual_input' => (bool) ($x['needs_manual_input'] ?? false),
                    'ai_extracted' => $x,
                    'ai_extracted_at' => $x ? now() : null,
                    'match_score' => 0,
                    'matched_keywords' => [],
                    'posted_at' => $post['posted_at'],
                ]);
                if (! $outcome['created']) {
                    $merged++;
                    $this->line('  = merged into #' . $outcome['job']->id . ' (same job, different wording)');
                    continue;
                }
                $stored++;
                $this->line('  + ' . ($emailApply ? '[email] ' : '[link]  ') . Str::limit($x['title'] ?? $post['text'], 70, '…'));
            }
        }

        $this->info("Done. {$seen} post(s) seen, {$kept} passed the free filters, stored {$stored}, merged {$merged} cross-post(s) into jobs we already had.");
        if ($aiBudget <= 0 && $kept > 0) {
            $this->line('AI extraction cap reached this run; remaining posts are picked up next run.');
        }

        // Non-English posts get translated once, like board listings.
        if ($stored > 0) {
            $this->call('jobs:translate');
        }

        return self::SUCCESS;
    }
}
