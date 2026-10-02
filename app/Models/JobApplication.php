<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    public const STATUSES = ['new' => 'New', 'reviewing' => 'Reviewing', 'shortlisted' => 'Shortlisted', 'rejected' => 'Rejected', 'hired' => 'Hired'];

    protected $fillable = ['job_listing_id', 'user_id', 'resume_id', 'cover_letter', 'resume_pdf_path', 'status', 'employer_note', 'applied_at'];

    protected $casts = ['applied_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
