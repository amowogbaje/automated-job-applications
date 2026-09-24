<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class JobListing extends Model
{
    protected $fillable = [
        'source', 'external_id', 'title', 'company', 'location',
        'is_remote', 'description', 'url', 'url_hash',
        'apply_method', 'apply_email',
        'match_score', 'matched_keywords', 'posted_at',
        'tailored_resume_path', 'tailored_resume_snapshot', 'tailored_for_resume_id', 'tailored_resume_generated_at',
        'tailored_cover_letter', 'tailored_cover_letter_generated_at',
    ];

    protected $casts = [
        'matched_keywords' => 'array',
        'tailored_resume_snapshot' => 'array',
        'posted_at' => 'datetime',
        'tailored_resume_generated_at' => 'datetime',
        'tailored_cover_letter_generated_at' => 'datetime',
        'is_remote' => 'boolean',
    ];

    public function applicationDrafts()
    {
        return $this->hasMany(ApplicationDraft::class);
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
