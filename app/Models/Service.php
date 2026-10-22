<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $guarded = [];

    protected $casts = [
        'skills_required' => 'array',
        'target_businesses' => 'array',
        'opportunity_signals' => 'array',
        'search_keywords' => 'array',
        'geographic_preferences' => 'array',
        'work_modes' => 'array',
        'is_employment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function portfolioProjects(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioProject::class, 'service_portfolio_project')
            ->withPivot('relevance')->withTimestamps();
    }

    public function cvVersions(): BelongsToMany
    {
        return $this->belongsToMany(CvVersion::class, 'service_cv_version')->withTimestamps();
    }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_services')
            ->withPivot('match_strength', 'is_primary', 'rationale')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
