<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSearch extends Model
{
    protected $fillable = [
        'user_id', 'query', 'stacks', 'countries', 'regions', 'results_count', 'new_leads_count',
    ];

    protected $casts = [
        'stacks' => 'array',
        'countries' => 'array',
        'regions' => 'array',
    ];

    // Hunter's free tier: 25 Discover calls/month. Kept here as a single
    // source of truth so the usage bar and the "you're near the cap"
    // warning can't drift out of sync with each other.
    public const FREE_TIER_MONTHLY_LIMIT = 25;

    public static function usedThisMonth(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
