<?php

namespace App\Services\Matching;

use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\JobListingUserState;

/**
 * Decides, once per (user, job), whether a listing is wanted — and stores the verdict.
 *
 * The user chooses the skills they want jobs for (CareerProfile::required_skills / keywords). When
 * jobs are ingested, `jobs:match` scores every new listing against every profile and writes the
 * result onto job_listing_user_states (score, tier, matched skills, ...). The feed and the email
 * alerts then read those rows with plain SQL: nothing is re-scored when a page opens, and a job that
 * isn't for you simply never appears in your feed or your inbox.
 *
 * Each profile keeps a watermark (last_matched_id) so a run only looks at listings it hasn't seen.
 * Changing your skills clears your stored matches and re-scores the recent window (backfill).
 */
class JobMatchService
{
    /** How far back a (re)match looks. Older listings are stale anyway. */
    public const WINDOW_DAYS = 14;

    /** Newest listings scored when someone changes their skills (keeps the save fast). */
    public const BACKFILL_LIMIT = 4000;

    private const INCREMENTAL_LIMIT = 20000;
    private const CHUNK = 300;

    /**
     * Score new listings for every profile that wants jobs.
     *
     * @return array{profiles:int, matches:int}
     */
    public function matchAll(): array
    {
        $profiles = 0;
        $matches = 0;

        CareerProfile::query()->orderBy('id')->each(function (CareerProfile $profile) use (&$profiles, &$matches) {
            $profiles++;
            $matches += $this->matchNew($profile);
        });

        return ['profiles' => $profiles, 'matches' => $matches];
    }

    /** Score listings added since this profile was last matched. Returns the number of new matches. */
    public function matchNew(CareerProfile $profile): int
    {
        // Paused or no skills chosen: leave the watermark alone so nothing is skipped when they come back.
        if (! $profile->open_to_work || ! $profile->hasInterests()) {
            return 0;
        }

        return $this->run($profile, (int) $profile->last_matched_id, self::INCREMENTAL_LIMIT);
    }

    /**
     * The person changed what they want: forget the old verdicts (keeping applied / dismissed /
     * notified flags) and score the recent window again.
     */
    public function backfill(CareerProfile $profile): int
    {
        JobListingUserState::query()->where('user_id', $profile->user_id)->update([
            'score' => null, 'tier' => null, 'matched' => null,
            'title_hits' => null, 'warnings' => null, 'matched_at' => null,
        ]);

        $profile->forceFill(['last_matched_id' => 0])->save();

        if (! $profile->open_to_work || ! $profile->hasInterests()) {
            return 0;
        }

        return $this->run($profile, 0, self::BACKFILL_LIMIT);
    }

    private function run(CareerProfile $profile, int $sinceId, int $limit): int
    {
        $userId = (int) $profile->user_id;
        $kinds = $profile->social_jobs_enabled ? ['job', 'social'] : ['job'];

        $ids = JobListing::query()
            ->canonical()
            ->whereIn('kind', $kinds)
            ->where('id', '>', $sinceId)
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $found = 0;

        foreach ($ids->chunk(self::CHUNK) as $chunk) {
            $jobs = JobListing::query()->whereIn('id', $chunk->all())->get();
            $existing = JobListingUserState::query()
                ->where('user_id', $userId)
                ->whereIn('job_listing_id', $chunk->all())
                ->get()
                ->keyBy('job_listing_id');

            $inserts = [];
            $now = now();

            foreach ($jobs as $job) {
                $r = $profile->score($job->scoringTitle(), $job->scoringDescription());
                if (! $r['matches']) {
                    continue;
                }

                $verdict = [
                    'score' => min(65535, (int) $r['score']),
                    'tier' => $r['tier'],
                    'matched' => $r['matched'],
                    'title_hits' => $r['title_hits'],
                    'warnings' => $r['warnings'],
                    'matched_at' => $now,
                ];
                $found++;

                if ($state = $existing->get($job->id)) {
                    $state->forceFill($verdict)->save();
                    continue;
                }

                $inserts[] = [
                    'job_listing_id' => $job->id,
                    'user_id' => $userId,
                    'score' => $verdict['score'],
                    'tier' => $verdict['tier'],
                    'matched' => json_encode($verdict['matched']),
                    'title_hits' => json_encode($verdict['title_hits']),
                    'warnings' => json_encode($verdict['warnings']),
                    'matched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($inserts) {
                JobListingUserState::query()->insert($inserts);
            }
        }

        $profile->forceFill(['last_matched_id' => max((int) $profile->last_matched_id, (int) $ids->max())])->save();

        return $found;
    }
}
