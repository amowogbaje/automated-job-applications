<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class JobListing extends Model
{
    protected $fillable = [
        'source', 'kind', 'external_id', 'title', 'company', 'location',
        'is_remote', 'description', 'url', 'url_hash',
        'apply_method', 'apply_email', 'ats', 'ats_board', 'ats_job_id', 'apply_url',
        'match_score', 'matched_keywords', 'posted_at',
        'tailored_resume_path', 'tailored_resume_snapshot', 'tailored_for_resume_id', 'tailored_resume_generated_at',
        'tailored_cover_letter', 'tailored_cover_letter_generated_at',
        'language', 'translation_status', 'title_translated', 'description_translated',
        'translation_provider', 'translation_attempts', 'translated_at',
        'social_platform', 'social_author', 'apply_instructions', 'apply_subject',
        'needs_manual_input', 'ai_extracted', 'ai_extracted_at',
        'fingerprint', 'apply_key', 'simhash', 'canonical_id', 'also_posted',
        'employer_id', 'status', 'employment_type', 'salary_min', 'salary_max', 'salary_currency', 'skills', 'closes_at',
    ];

    protected $casts = [
        'matched_keywords' => 'array',
        'tailored_resume_snapshot' => 'array',
        'posted_at' => 'datetime',
        'tailored_resume_generated_at' => 'datetime',
        'tailored_cover_letter_generated_at' => 'datetime',
        'is_remote' => 'boolean',
        'translated_at' => 'datetime',
        'needs_manual_input' => 'boolean',
        'ai_extracted' => 'array',
        'ai_extracted_at' => 'datetime',
        'also_posted' => 'array',
        'skills' => 'array',
        'closes_at' => 'datetime',
    ];

    public const LANGUAGE_NAMES = [
        'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese',
        'nl' => 'Dutch', 'it' => 'Italian', 'pl' => 'Polish', 'sv' => 'Swedish',
    ];

    public function languageName(): ?string
    {
        return $this->language && $this->language !== 'en'
            ? (self::LANGUAGE_NAMES[$this->language] ?? strtoupper($this->language))
            : null;
    }

    public function isTranslated(): bool
    {
        return $this->translation_status === 'done' && filled($this->description_translated ?? $this->title_translated);
    }

    // Matching reads the original AND the translation, so a German posting is judged on
    // everything it says (skill names are mostly language-neutral, but "Berufserfahrung"-style
    // words around them aren't).
    /** True when a posting date is older than the 30-day cap (unknown dates are kept). */
    public static function tooOld(mixed $postedAt): bool
    {
        if (empty($postedAt)) {
            return false;
        }
        try {
            return \Illuminate\Support\Carbon::parse($postedAt)->lt(now()->subDays((int) config('jobs.max_age_days', 30)));
        } catch (\Throwable) {
            return false;
        }
    }

    public function scoringTitle(): string
    {
        return trim($this->title . ' ' . ($this->title_translated ?? ''));
    }

    public function scoringDescription(): string
    {
        // Native jobs declare their skills; the matcher reads them as part of the text.
        $skills = $this->skills ? 'Skills: ' . implode(', ', (array) $this->skills) . '. ' : '';

        return trim($skills . ($this->description ?? '') . ' ' . ($this->description_translated ?? ''));
    }

    public function isNative(): bool
    {
        return $this->source === 'native';
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function salaryLabel(): ?string
    {
        if (! $this->salary_min && ! $this->salary_max) {
            return null;
        }
        $cur = $this->salary_currency ?: 'USD';
        $fmt = fn ($n) => number_format((int) $n);

        return $this->salary_min && $this->salary_max
            ? "{$cur} {$fmt($this->salary_min)} – {$fmt($this->salary_max)}"
            : "{$cur} " . $fmt($this->salary_min ?: $this->salary_max) . ($this->salary_min ? '+' : ' max');
    }

    // Hidden duplicates (soft-merged into another listing) and unpublished/expired native jobs are never listed.
    public function scopeCanonical(Builder $query): Builder
    {
        return $query->whereNull('canonical_id')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'published'))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    // Regular board listings only (the job-board half of the /jobs feed).
    public function scopeBoard(Builder $query): Builder
    {
        return $query->where('kind', 'job')->canonical();
    }

    public function scopeSocial(Builder $query): Builder
    {
        return $query->where('kind', 'social')->canonical();
    }

    public function states()
    {
        return $this->hasMany(JobListingUserState::class);
    }

    // Get (or build, unsaved) this job's status for a specific user —
    // callers never need to null-check. Only persisted when something
    // actually changes it (see JobDashboardController::dismiss/markApplied).
    public function stateFor(int $userId): JobListingUserState
    {
        return $this->states->firstWhere('user_id', $userId)
            ?? new JobListingUserState(['job_listing_id' => $this->id, 'user_id' => $userId]);
    }

    public function scopeLast24Hours(Builder $query): Builder
    {
        return $query->where('posted_at', '>=', now()->subHours(24));
    }

    // Excludes jobs THIS user has dismissed. Everyone else's dismissals
    // don't affect what you see — that's the whole point of per-user state.
    public function scopeNotDismissedBy(Builder $query, int $userId): Builder
    {
        return $query->whereDoesntHave('states', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('is_dismissed', true);
        });
    }

    public function scopeNotAppliedBy(Builder $query, int $userId): Builder
    {
        return $query->whereDoesntHave('states', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('is_applied', true);
        });
    }

    public function scopeNotYetNotifiedFor(Builder $query, int $userId): Builder
    {
        return $query->whereDoesntHave('states', function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNotNull('notified_at');
        });
    }

    public function scopeMatching(Builder $query, ?string $keyword): Builder
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where(function ($q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
              ->orWhere('description', 'like', "%{$keyword}%");
        });
    }
}
