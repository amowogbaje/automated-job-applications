<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PortfolioProject extends Model
{
    protected $guarded = [];

    protected $casts = [
        'technologies' => 'array',
        'industries' => 'array',
        'is_public' => 'boolean',
    ];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_portfolio_project')
            ->withPivot('relevance')->withTimestamps();
    }
}
