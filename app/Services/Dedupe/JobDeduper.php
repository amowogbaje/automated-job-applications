<?php

namespace App\Services\Dedupe;

use App\Models\JobListing;
use App\Services\Social\SocialPostValidator;
use Illuminate\Support\Str;

/**
 * One place that decides "have we already got this job?". Every ingest path (job boards, ATS feeds,
 * social posts, Reddit/Lemmy, employer-posted jobs) goes through ingest(), so a role that appears on
 * Greenhouse, gets tweeted, and is cross-posted to Mastodon and Reddit is ONE listing, with the other
 * places recorded in `also_posted` (shown as "Also on: ...").
 *
 * Duplicates are never deleted: a duplicate is soft-merged (canonical_id points at the keeper), so
 * nothing referencing it breaks and the decision is reversible.
 */
class JobDeduper
{
    public const SIMHASH_MAX_DISTANCE = 3;
    private const WINDOW_DAYS = 45;
    private const TEXT_WINDOW_DAYS = 14;

    /** @var array<int,string>|null id => simhash, loaded once per run and kept current */
    private ?array $simhashIndex = null;

    /** @return array{job: JobListing, created: bool, merged: bool} */
    public function ingest(array $attrs): array
    {
        // Exact same link/hash seen before: nothing to do.
        if (! empty($attrs['url_hash']) && ($existing = JobListing::where('url_hash', $attrs['url_hash'])->first())) {
            return ['job' => $existing, 'created' => false, 'merged' => false];
        }

        $attrs = $this->withKeys($attrs);

        if ($dup = $this->findDuplicate($attrs)) {
            return ['job' => $this->merge($dup, $attrs), 'created' => false, 'merged' => true];
        }

        $job = JobListing::create($attrs);
        $this->register($job);

        return ['job' => $job, 'created' => true, 'merged' => false];
    }

    /** Add fingerprint / apply_key / simhash to an attribute array. */
    public function withKeys(array $a): array
    {
        $a['fingerprint'] = $a['fingerprint'] ?? JobFingerprint::fingerprint($a['company'] ?? null, $a['title'] ?? null);
        $a['apply_key'] = $a['apply_key'] ?? JobFingerprint::applyKey($a['ats'] ?? null, $a['ats_board'] ?? null, $a['ats_job_id'] ?? null, $a['apply_url'] ?? null);
        $a['simhash'] = $a['simhash'] ?? JobFingerprint::simhash(($a['title'] ?? '') . ' ' . ($a['description'] ?? ''));

        return $a;
    }

    public function findDuplicate(array $a, ?int $exceptId = null): ?JobListing
    {
        $recent = fn ($q) => $q->whereNull('canonical_id')
            ->when($exceptId, fn ($qq) => $qq->where('id', '!=', $exceptId))
            ->where(fn ($qq) => $qq->where('posted_at', '>=', now()->subDays(self::WINDOW_DAYS))->orWhereNull('posted_at'));

        // 1) literally the same posting: same ATS job id or same canonical apply link
        if (! empty($a['apply_key']) && ($hit = $recent(JobListing::query())->where('apply_key', $a['apply_key'])->orderBy('id')->first())) {
            return $hit;
        }

        // 2) same company + same normalised title
        if (! empty($a['fingerprint']) && ($hit = $recent(JobListing::query())->where('fingerprint', $a['fingerprint'])->orderBy('id')->first())) {
            return $hit;
        }

        // 3) near-identical wording (a post re-posted on another platform)
        if (! empty($a['simhash']) && ($id = $this->nearestSimhash($a['simhash'], $exceptId))) {
            return JobListing::find($id);
        }

        // 4) same "apply to this email" address with a similar title
        $emails = array_filter([$a['apply_email'] ?? null]);
        if ($emails) {
            $mine = explode(' ', JobFingerprint::normalizeTitle($a['title'] ?? ''));
            $cands = $recent(JobListing::query())->whereIn('apply_email', $emails)->limit(20)->get(['id', 'title']);
            foreach ($cands as $c) {
                if ($this->overlap($mine, explode(' ', JobFingerprint::normalizeTitle($c->title))) >= 0.5) {
                    return JobListing::find($c->id);
                }
            }
        }

        return null;
    }

    /**
     * Cheap pre-check for social posts BEFORE any AI is spent: is this post's text (or an email in it)
     * already covered by a stored listing? Returns the keeper, or null.
     */
    public function findByText(string $text): ?JobListing
    {
        $hash = JobFingerprint::simhash($text);
        if ($hash && ($id = $this->nearestSimhash($hash))) {
            return JobListing::find($id);
        }

        $emails = SocialPostValidator::extractEmails($text);
        if ($emails) {
            return JobListing::query()->whereNull('canonical_id')
                ->whereIn('apply_email', $emails)
                ->where('posted_at', '>=', now()->subDays(self::TEXT_WINDOW_DAYS))
                ->orderBy('id')->first();
        }

        return null;
    }

    /**
     * Record that $keeper also appeared somewhere else, and fill in anything the keeper lacks.
     * If a regular board listing turns up for something first seen as a social post, the board listing's
     * data wins (it has the real apply link / ATS ids) and the social post becomes an "also posted on".
     */
    public function merge(JobListing $keeper, array $incoming): JobListing
    {
        $also = (array) ($keeper->also_posted ?? []);
        $known = array_filter(array_merge([$keeper->url], array_column($also, 'url')));

        $promote = ($incoming['kind'] ?? 'job') === 'job' && $keeper->kind === 'social' && ! $keeper->isNative();
        $update = [];

        if ($promote) {
            // The social post moves into "also posted"; the board listing's data becomes the main record.
            if (! in_array($keeper->url, array_column($also, 'url'), true)) {
                $also[] = $this->entry(['source' => $keeper->source, 'url' => $keeper->url, 'posted_at' => $keeper->posted_at, 'platform' => $keeper->social_platform]);
            }
            foreach (['source', 'kind', 'url', 'url_hash', 'title', 'company', 'description', 'apply_method', 'apply_email', 'apply_url', 'ats', 'ats_board', 'ats_job_id', 'external_id', 'employment_type', 'language', 'translation_status'] as $f) {
                if (array_key_exists($f, $incoming) && filled($incoming[$f])) {
                    $update[$f] = $incoming[$f];
                }
            }
            $update['apply_instructions'] = $keeper->apply_instructions; // keep what the poster said
        } else {
            if (! empty($incoming['url']) && ! in_array($incoming['url'], $known, true)) {
                $also[] = $this->entry($incoming);
            }
            // Fill gaps only; never overwrite what the keeper already has.
            foreach (['company', 'location', 'apply_email', 'apply_url', 'ats', 'ats_board', 'ats_job_id', 'apply_instructions', 'apply_subject'] as $f) {
                if (blank($keeper->{$f}) && ! empty($incoming[$f])) {
                    $update[$f] = $incoming[$f];
                }
            }
            if (! empty($update['apply_email']) && $keeper->apply_method !== 'email' && ($incoming['apply_method'] ?? null) === 'email') {
                $update['apply_method'] = 'email';
            }
        }

        $update['also_posted'] = array_values($also);
        $keeper->fill($update);
        $keeper->fill($this->withKeys(array_merge($keeper->only(['company', 'title', 'description', 'ats', 'ats_board', 'ats_job_id', 'apply_url']), ['fingerprint' => $keeper->fingerprint, 'apply_key' => $keeper->apply_key, 'simhash' => $keeper->simhash])));
        $keeper->save();

        return $keeper;
    }

    /** Add a "also posted on" entry from just a source/url/time (used when skipping the AI for a cross-post). */
    public function noteCrossPost(JobListing $keeper, string $source, string $url, $postedAt = null, ?string $platform = null): JobListing
    {
        return $this->merge($keeper, ['source' => $source, 'url' => $url, 'posted_at' => $postedAt, 'social_platform' => $platform, 'kind' => 'social']);
    }

    /**
     * An employer just published a native job: hide every existing copy of it elsewhere (boards, tweets)
     * under the native listing, which has in-app applications. Returns how many were merged.
     */
    public function adopt(JobListing $native): int
    {
        $native = $this->refreshKeys($native);
        $n = 0;

        $q = JobListing::query()->whereNull('canonical_id')->where('id', '!=', $native->id)->where('source', '!=', 'native')
            ->where(function ($q) use ($native) {
                $any = false;
                foreach (['fingerprint', 'apply_key'] as $col) {
                    if ($native->{$col}) {
                        $q->orWhere($col, $native->{$col});
                        $any = true;
                    }
                }
                if (! $any) {
                    $q->whereRaw('1 = 0');
                }
            });

        foreach ($q->get() as $dup) {
            $also = (array) ($native->also_posted ?? []);
            $also[] = $this->entry(['source' => $dup->source, 'url' => $dup->url, 'posted_at' => $dup->posted_at, 'platform' => $dup->social_platform]);
            $native->also_posted = $also;
            $dup->update(['canonical_id' => $native->id]);
            $n++;
        }
        $native->save();

        return $n;
    }

    public function refreshKeys(JobListing $job): JobListing
    {
        $keys = $this->withKeys(['company' => $job->company, 'title' => $job->title, 'description' => $job->description, 'ats' => $job->ats, 'ats_board' => $job->ats_board, 'ats_job_id' => $job->ats_job_id, 'apply_url' => $job->apply_url]);
        $job->fill(['fingerprint' => $keys['fingerprint'], 'apply_key' => $keys['apply_key'], 'simhash' => $keys['simhash']])->save();

        return $job;
    }

    /** Called after a listing is created so later checks in the same run can see it. */
    public function register(JobListing $job): void
    {
        if ($this->simhashIndex !== null && $job->simhash) {
            $this->simhashIndex[$job->id] = $job->simhash;
        }
    }

    private function nearestSimhash(string $hash, ?int $exceptId = null): ?int
    {
        if ($this->simhashIndex === null) {
            $this->simhashIndex = JobListing::query()->whereNull('canonical_id')->whereNotNull('simhash')
                ->where('posted_at', '>=', now()->subDays(self::TEXT_WINDOW_DAYS))
                ->pluck('simhash', 'id')->all();
        }

        $best = null;
        $bestD = self::SIMHASH_MAX_DISTANCE + 1;
        foreach ($this->simhashIndex as $id => $other) {
            if ($exceptId && $id === $exceptId) {
                continue;
            }
            $d = JobFingerprint::distance($hash, $other);
            if ($d < $bestD) {
                $best = $id;
                $bestD = $d;
            }
        }

        return $best;
    }

    private function entry(array $a): array
    {
        return [
            'source' => $a['source'] ?? 'unknown',
            'platform' => $a['platform'] ?? ($a['social_platform'] ?? null),
            'url' => $a['url'] ?? null,
            'posted_at' => isset($a['posted_at']) && $a['posted_at'] ? (string) \Illuminate\Support\Carbon::parse($a['posted_at'])->toIso8601String() : null,
        ];
    }

    private function overlap(array $a, array $b): float
    {
        $a = array_filter(array_unique($a));
        $b = array_filter(array_unique($b));
        if (! $a || ! $b) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / count(array_unique(array_merge($a, $b)));
    }
}
