<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeEducation extends Model
{
    protected $table = 'resume_education';

    protected $fillable = [
        'resume_id', 'institution', 'degree', 'field',
        'start_date', 'end_date', 'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
