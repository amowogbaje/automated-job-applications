<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'alt_subjects' => 'array',
        'evidence_ids' => 'array',
        'claims' => 'array',
        'warnings' => 'array',
        'edited_at' => 'datetime',
        'is_selected' => 'boolean',
        'copied_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function portfolioProject(): BelongsTo
    {
        return $this->belongsTo(PortfolioProject::class);
    }

    public function cvVersion(): BelongsTo
    {
        return $this->belongsTo(CvVersion::class);
    }

    /** Message text exactly as it should be pasted: body, then the chosen signature (if any). */
    public function fullText(): string
    {
        $sig = $this->signature?->body;

        return rtrim($this->body).($sig ? "\n\n".trim($sig) : '');
    }
}
