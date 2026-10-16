<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('step_number');
    }

    /** Shape used by the research panel (initial render and polling). */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'error' => $this->error,
            'summary' => $this->result_summary,
            'model' => $this->ai_model,
            'url' => route('agent-runs.show', $this),
            'steps' => $this->steps->map(fn ($s) => [
                'n' => $s->step_number, 'tool' => $s->tool, 'ok' => (bool) $s->succeeded,
                'ms' => $s->duration_ms, 'text' => mb_substr((string) ($s->succeeded ? $s->output : $s->error), 0, 400),
            ])->all(),
        ];
    }
}
