<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CvVersion extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean', 'snapshot' => 'array'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_cv_version')->withTimestamps();
    }
}
