<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeProject extends Model
{
    protected $fillable = ['resume_id', 'name', 'description', 'tech_stack', 'url', 'sort_order'];

    protected $casts = [
        'tech_stack' => 'array',
    ];

    protected static function booted(): void
    {
        // Keep the agent's project list in step with the Profile (import, edits, deletes).
        static::saved(function (self $p) {
            try {
                $sync = app(\App\Services\Agent\Profile\ProfileSync::class);
                if ($p->resume && $sync->isOwnersResume($p->resume) && \Illuminate\Support\Facades\Schema::hasColumn('portfolio_projects', 'resume_project_id')) {
                    $sync->syncProject($p);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
