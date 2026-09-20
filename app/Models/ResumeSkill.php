<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeSkill extends Model
{
    protected $fillable = ['resume_id', 'category', 'name', 'sort_order'];

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
