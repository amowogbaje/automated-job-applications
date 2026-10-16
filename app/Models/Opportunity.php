<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    public const STATUSES = [
        'discovered', 'researching', 'qualified', 'rejected', 'drafted',
        'approved', 'contacted', 'replied', 'won', 'lost', 'archived',
    ];

    public const SCORE_CRITERIA = [
        'need_evidence', 'skills_match', 'active_signal',
        'commercial_value', 'contact_channel', 'portfolio_strength',
    ];

    protected $guarded = [];

    protected $casts = [
        'budget_is_verified' => 'boolean',
        'posted_at' => 'date',
        'expires_at' => 'date',
        'qualified_at' => 'datetime',
        'contacted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(DiscoveryRun::class);
    }

    public function primaryService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'primary_service_id');
    }

    public function recommendedCv(): BelongsTo
    {
        return $this->belongsTo(CvVersion::class, 'recommended_cv_version_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'opportunity_services')
            ->withPivot('match_strength', 'is_primary', 'rationale')->withTimestamps();
    }

    public function portfolioProjects(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioProject::class, 'opportunity_portfolio_project')
            ->withPivot('reason', 'rank')->withTimestamps();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(OpportunityScore::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function outreachDrafts(): HasMany
    {
        return $this->hasMany(OutreachDraft::class);
    }

    public function outreachEvents(): HasMany
    {
        return $this->hasMany(OutreachEvent::class)->latest('occurred_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /** Weighted total from stored component rows (weights saved at scoring time). */
    public function recalculateScore(): int
    {
        $rows = $this->scores()->get();
        $weightSum = max(1, $rows->sum('weight'));
        $total = (int) round($rows->sum(fn ($r) => $r->score * $r->weight) / $weightSum);

        $thresholds = Setting::get('priority_thresholds', ['A' => 75, 'B' => 55]);

        $this->total_score = $total;
        $this->priority = match (true) {
            $this->status === 'rejected' => null,
            $total >= ($thresholds['A'] ?? 75) => 'A',
            $total >= ($thresholds['B'] ?? 55) => 'B',
            default => 'C',
        };
        $this->save();

        return $total;
    }

    public function scopeActionable($query)
    {
        return $query->whereNotIn('status', ['rejected', 'archived', 'won', 'lost']);
    }
}
