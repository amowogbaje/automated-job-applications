<?php

namespace App\Models;

use App\Services\Agent\Leads\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappReminder extends Model
{
    protected $guarded = [];

    protected $casts = ['remind_at' => 'datetime', 'notified_at' => 'datetime', 'sent_at' => 'datetime'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** The click-to-chat link: opens WhatsApp with the number and the message already filled in. */
    public function waUrl(): string
    {
        return Phone::waLink($this->phone, $this->message);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['pending', 'due']);
    }
}
