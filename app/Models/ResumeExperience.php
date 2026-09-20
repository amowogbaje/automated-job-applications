<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeExperience extends Model
{
    protected $fillable = [
        'resume_id', 'job_title', 'company', 'location',
        'start_date', 'end_date', 'is_current', 'bullets', 'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'bullets' => 'array',
    ];

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }

    public function dateRangeLabel(): string
    {
        $start = $this->start_date?->format('M Y') ?? '';
        $end = $this->is_current ? 'Present' : ($this->end_date?->format('M Y') ?? '');

        return trim("{$start} – {$end}", ' –');
    }
}
