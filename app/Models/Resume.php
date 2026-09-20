<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resume extends Model
{
    protected $fillable = [
        'user_id', 'source', 'is_active', 'full_name', 'headline', 'email', 'phone',
        'location', 'website_url', 'linkedin_url', 'github_url', 'summary',
        'raw_pdf_text', 'raw_website_text',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(ResumeExperience::class)->orderBy('sort_order');
    }

    public function education(): HasMany
    {
        return $this->hasMany(ResumeEducation::class)->orderBy('sort_order');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(ResumeSkill::class)->orderBy('sort_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ResumeProject::class)->orderBy('sort_order');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(ResumeCertification::class)->orderBy('sort_order');
    }

    /**
     * The resume currently used for AI drafting and PDF compilation.
     * Defaults to the logged-in user; falls back to the only/first user in
     * the table for CLI/scheduler contexts where there's no web session —
     * this app is built for a single operator per install for now, not
     * multi-tenant job processing (see README).
     */
    public static function current(?int $userId = null): ?self
    {
        $userId ??= auth()->id() ?? User::query()->value('id');

        return static::query()
            ->with(['experiences', 'education', 'skills', 'projects', 'certifications'])
            ->where('is_active', true)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('id')
            ->first();
    }

    /**
     * Mark this resume as the active one and deactivate the same user's
     * other resumes (leaves other users' resumes untouched).
     */
    public function activate(): void
    {
        static::query()
            ->where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update(['is_active' => true]);
    }

    public function skillsByCategory(): \Illuminate\Support\Collection
    {
        return $this->skills->groupBy('category')->map(fn ($group) => $group->pluck('name'));
    }
}
