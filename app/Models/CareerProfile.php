<?php

namespace App\Models;

use App\Services\Matching\JobMatcher;
use App\Services\Skills\SkillNormalizer;
use Illuminate\Database\Eloquent\Model;

class CareerProfile extends Model
{
    protected $fillable = [
        'user_id', 'keywords', 'required_skills', 'excluded_keywords', 'min_required_matches',
        'notification_email', 'auto_send_enabled', 'digest_enabled',
        'auto_apply_mode', 'daily_apply_cap', 'ats_boards', 'screening_answers',
        'hide_region_restricted', 'open_to_work', 'social_jobs_enabled', 'social_auto_email',
    ];

    protected $casts = [
        'keywords' => 'array',
        'required_skills' => 'array',
        'excluded_keywords' => 'array',
        'min_required_matches' => 'integer',
        'auto_send_enabled' => 'boolean',
        'digest_enabled' => 'boolean',
        'ats_boards' => 'array',
        'screening_answers' => 'array',
        'daily_apply_cap' => 'integer',
        'hide_region_restricted' => 'boolean',
        'open_to_work' => 'boolean',
        'social_jobs_enabled' => 'boolean',
        'social_auto_email' => 'boolean',
    ];

    // What every account starts with — the exact values that used to be
    // hardcoded in .env, so migrating to per-user profiles doesn't change
    // anything for the existing operator until they deliberately edit
    // their own profile at /profile.
    public const DEFAULTS = [
        'keywords' => ['laravel', 'php', 'full-stack', 'vue', 'react'],
        'required_skills' => ['laravel', 'php'],
        'excluded_keywords' => ['wordpress', 'junior', 'unpaid', 'internship'],
        'min_required_matches' => 1,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Where digests/applications get sent for this account — an explicit
    // override if set, otherwise the account's own login email.
    public function notificationEmail(): ?string
    {
        return $this->notification_email ?: $this->user?->email;
    }

    /**
     * Get (or lazily create, with sane defaults) the profile for a user.
     * Mirrors Resume::current()'s fallback: defaults to the logged-in
     * user, then the only/first user for CLI/scheduler contexts. Always
     * returns a real object — callers never need to null-check.
     */
    public static function forUser(?int $userId = null): self
    {
        $userId ??= auth()->id() ?? User::query()->value('id');

        if (! $userId) {
            return new static(self::DEFAULTS);
        }

        return static::firstOrCreate(['user_id' => $userId], self::DEFAULTS);
    }

    /**
     * The same matching algorithm FetchJobs used to run against .env
     * config, now run against this profile's own settings. Takes plain
     * text so it works identically before a JobListing is saved (during
     * ingestion) and after (scoring an existing row for a dashboard).
     *
     * @return array{matches:bool, score:int, matched:string[], tier:?string, title_hits:string[], generic_hits:string[], warnings:string[], excluded_by:?string}
     */
    public function score(string $title, ?string $description): array
    {
        return app(JobMatcher::class)->score(
            $title,
            $description,
            required: $this->normalized('required_skills')->all(),
            keywords: $this->normalized('keywords')->all(),
            excluded: $this->normalized('excluded_keywords')->all(),
            minRequired: (int) $this->min_required_matches,
            hideRegionRestricted: (bool) $this->hide_region_restricted,
        );
    }

    /**
     * Pull keywords + required skills straight from a resume's skill
     * list — the "set your profile from your resume" button. Doesn't
     * touch excluded_keywords or min_required_matches; which terms to
     * exclude and how strict to be are judgment calls a resume can't
     * answer for you.
     */
    public function fillFromResume(Resume $resume): void
    {
        // Resume skills are already atomic + canonical (see Resume::syncSkills);
        // normalizing again is a cheap guard for rows that predate it.
        $skillNames = app(SkillNormalizer::class)->names($resume->skills->pluck('name')->all());

        // Required = the CORE stack only. Broad skills (Git, REST, SQL, Agile...)
        // stay as keywords: if they were "required", any job mentioning "REST"
        // would qualify (see JobMatcher).
        $normalizer = app(SkillNormalizer::class);
        $core = array_values(array_filter($skillNames, fn ($n) => ! $normalizer->isGeneric($n)));

        $this->keywords = $skillNames;
        $this->required_skills = $core ?: $skillNames;
    }

    // excluded_keywords are plain words ("junior", "unpaid") — lowercase only.
    // keywords / required_skills hold canonical skill names and are matched
    // via SkillNormalizer, so they keep their display casing.
    private function normalized(string $field): \Illuminate\Support\Collection
    {
        $items = collect($this->{$field} ?? [])->map(fn ($k) => trim((string) $k))->filter()->values();

        if ($field === 'excluded_keywords') {
            return $items->map(fn ($k) => strtolower($k))->values();
        }

        return collect(app(SkillNormalizer::class)->names($items->all()));
    }
}
