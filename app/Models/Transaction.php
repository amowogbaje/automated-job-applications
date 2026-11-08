<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'occurred_on' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
