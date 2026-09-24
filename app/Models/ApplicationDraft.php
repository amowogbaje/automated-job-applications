<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDraft extends Model
{
    protected $fillable = [
        'job_listing_id', 'user_id', 'cover_letter', 'tailored_summary',
        'resume_snapshot', 'resume_pdf_path', 'status',
    ];

    protected $casts = [
        'resume_snapshot' => 'array',
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
