<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSequence extends Model
{
    protected $guarded = [];

    protected $casts = ['auto_send' => 'boolean', 'next_action_at' => 'datetime', 'last_sent_at' => 'datetime'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
