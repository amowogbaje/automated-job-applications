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
        'is_applied', 'applied_at', 'notified_at', 'is_dismissed',
    ];

    protected $casts = [
        'matched_keywords' => 'array',
        'posted_at' => 'datetime',
        'applied_at' => 'datetime',
        'notified_at' => 'datetime',
        'is_remote' => 'boolean',
        'is_applied' => 'boolean',
        'is_dismissed' => 'boolean',
    ];

    public function applicationDraft()
    {
        return $this->hasOne(ApplicationDraft::class);
    }

    public function scopeNotYetProcessed(Builder $query): Builder
    {
        return $query->where('is_applied', false)->whereNull('notified_at');
    }

    public function scopeLast24Hours(Builder $query): Builder
    {
        return $query->where('posted_at', '>=', now()->subHours(24));
    }

    public function scopeNotDismissed(Builder $query): Builder
    {
        return $query->where('is_dismissed', false);
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
