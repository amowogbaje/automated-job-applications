<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantProfile extends Model
{
    protected $fillable = ['resume_raw_text', 'skills', 'summary', 'projects'];

    protected $casts = [
        'skills' => 'array',
        'projects' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new self();
    }
}
