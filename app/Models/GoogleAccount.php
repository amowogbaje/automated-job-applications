<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAccount extends Model
{
    protected $guarded = [];

    protected $hidden = ['refresh_token', 'access_token'];

    protected $casts = ['refresh_token' => 'encrypted', 'access_token' => 'encrypted', 'expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
