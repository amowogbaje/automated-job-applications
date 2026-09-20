<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDraft extends Model
{
    protected $fillable = ['job_listing_id', 'cover_letter', 'tailored_summary', 'status'];

    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }
}
