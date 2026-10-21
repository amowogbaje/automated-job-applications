<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PortfolioProject extends Model
{
    protected $guarded = [];

    protected $casts = [
        'technologies' => 'array',
        'industries' => 'array',
        'is_public' => 'boolean',
    ];

    public function resumeProject(): BelongsTo
    {
        return $this->belongsTo(ResumeProject::class);
    }

    /** True when name, URL, summary and technologies come from the Profile and are edited there. */
    public function isFromProfile(): bool
    {
        return $this->resume_project_id !== null;
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_portfolio_project')
            ->withPivot('relevance')->withTimestamps();
    }
}
