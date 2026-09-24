<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobListingUserState extends Model
{
    protected $fillable = [
        'job_listing_id', 'user_id', 'is_applied', 'applied_at',
        'is_dismissed', 'dismissed_at', 'notified_at',
    ];

    protected $casts = [
        'is_applied' => 'boolean',
        'is_dismissed' => 'boolean',
        'applied_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'notified_at' => 'datetime',
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
