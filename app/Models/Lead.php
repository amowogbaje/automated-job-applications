<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'domain', 'country_code', 'industry', 'headcount',
        'tech_stack', 'contact_name', 'contact_email', 'contact_position',
        'source', 'status', 'notes', 'contacted_at',
    ];

    protected $casts = [
        'tech_stack' => 'array',
        'contacted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function markContacted(): void
    {
        $this->update(['status' => 'contacted', 'contacted_at' => now()]);
    }
}
