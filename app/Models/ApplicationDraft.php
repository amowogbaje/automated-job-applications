<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDraft extends Model
{
    protected $fillable = [
        'job_listing_id', 'user_id', 'cover_letter', 'tailored_summary',
        'resume_snapshot', 'resume_pdf_path', 'status',
        'apply_channel', 'external_ref', 'submitted_at', 'receipt_url', 'pending_questions', 'error',
    ];

    protected $casts = [
        'resume_snapshot' => 'array',
        'pending_questions' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
