<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeProject extends Model
{
    protected $fillable = ['resume_id', 'name', 'description', 'tech_stack', 'url', 'sort_order'];

    protected $casts = [
        'tech_stack' => 'array',
    ];

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
