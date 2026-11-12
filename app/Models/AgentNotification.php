<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentNotification extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public static function unreadCount(): int
    {
        try {
            return static::whereNull('read_at')->count();
        } catch (\Throwable) {
            return 0; // table missing before `php artisan migrate`
        }
    }

    /** Creates once per $key; returns null if it already existed. */
    public static function raise(string $kind, string $title, ?string $body, ?int $opportunityId, string $key, ?string $url = null): ?self
    {
        if (static::where('dedupe_key', $key)->exists()) {
            return null;
        }

        $data = ['kind' => $kind, 'title' => $title, 'body' => $body, 'opportunity_id' => $opportunityId, 'dedupe_key' => $key];
        if ($url && static::hasUrlColumn()) { // tolerate a database that has not had the newest migration yet
            $data['url'] = $url;
        }

        return static::create($data);
    }

    private static ?bool $urlColumn = null;

    public static function hasUrlColumn(): bool
    {
        try {
            return static::$urlColumn ??= \Illuminate\Support\Facades\Schema::hasColumn('agent_notifications', 'url');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Wake the owner's devices (push). Never lets a push problem break the follow-up run. */
    public static function pushAll(): void
    {
        try {
            app(\App\Services\Agent\Push\WebPush::class)->broadcast();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
