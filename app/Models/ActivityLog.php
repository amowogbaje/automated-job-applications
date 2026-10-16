<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $guarded = [];

    protected $casts = ['properties' => 'array'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(Model $subject, string $action, ?string $description = null, array $properties = []): self
    {
        return static::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
