<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeCertification extends Model
{
    protected $table = 'resume_certifications';

    protected $fillable = ['resume_id', 'name', 'sort_order'];

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }
}
