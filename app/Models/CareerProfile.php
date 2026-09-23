<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerProfile extends Model
{
    protected $fillable = [
        'user_id', 'keywords', 'required_skills', 'excluded_keywords', 'min_required_matches',
    ];

    protected $casts = [
        'keywords' => 'array',
        'required_skills' => 'array',
        'excluded_keywords' => 'array',
        'min_required_matches' => 'integer',
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
     * @return array{matches: bool, score: int, matched: string[]}
     */
    public function score(string $title, ?string $description): array
    {
        $text = strtolower($title . ' ' . ($description ?? ''));
        $none = ['matches' => false, 'score' => 0, 'matched' => []];

        $excluded = $this->normalized('excluded_keywords');
        if ($excluded->isNotEmpty() && $excluded->contains(fn ($k) => str_contains($text, $k))) {
            return $none;
        }

        $required = $this->normalized('required_skills');
        $requiredHits = $required->filter(fn ($k) => str_contains($text, $k))->values();

        if ($required->isNotEmpty() && $requiredHits->count() < max(1, $this->min_required_matches)) {
            return $none;
        }

        $keywords = $this->normalized('keywords');
        $niceHits = $keywords->filter(fn ($k) => str_contains($text, $k))->values();
        $matched = $requiredHits->merge($niceHits)->unique()->values();

        // No required_skills configured at all? Fall back to needing at
        // least one "nice to have" keyword, same as the old .env behavior.
        if ($required->isEmpty() && $keywords->isNotEmpty() && $matched->isEmpty()) {
            return $none;
        }

        return [
            'matches' => true,
            'score' => $requiredHits->count() * 2 + $niceHits->count(),
            'matched' => $matched->all(),
        ];
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
        $skillNames = $resume->skills
            ->pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->keywords = $skillNames;
        $this->required_skills = $skillNames;
    }

    private function normalized(string $field): \Illuminate\Support\Collection
    {
        return collect($this->{$field} ?? [])
            ->map(fn ($k) => strtolower(trim($k)))
            ->filter()
            ->values();
    }
}
