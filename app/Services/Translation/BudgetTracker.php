<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Cache;

/**
 * Local per-driver bookkeeping so free tiers are used up in turn instead of being blown through:
 *  - character counters (daily or monthly windows), checked BEFORE a driver is used;
 *  - an "exhausted" flag, set the moment a provider itself says it's out of quota (or rate-limits us),
 *    so every following listing goes straight to the next driver until the window resets.
 */
class BudgetTracker
{
    public static function used(string $driver, string $period): int
    {
        return (int) Cache::get(self::key($driver, $period), 0);
    }

    public static function add(string $driver, string $period, int $chars): void
    {
        $key = self::key($driver, $period);
        // Cache::add sets only when missing, so the TTL is fixed from first use in the window.
        Cache::add($key, 0, $period === 'month' ? now()->addDays(32) : now()->addDays(2));
        Cache::increment($key, $chars);
    }

    /** The provider told us its quota is gone: skip it until the day/month rolls over. */
    public static function exhaust(string $driver, string $period): void
    {
        $until = $period === 'month' ? now()->endOfMonth()->addSecond() : now()->endOfDay()->addSecond();
        Cache::put(self::pausedKey($driver), $until->toIso8601String(), $until);
    }

    /** Short back-off (rate limit, temporary outage). */
    public static function pause(string $driver, int $minutes = 10): void
    {
        $until = now()->addMinutes($minutes);
        Cache::put(self::pausedKey($driver), $until->toIso8601String(), $until);
    }

    public static function pausedUntil(string $driver): ?string
    {
        return Cache::get(self::pausedKey($driver));
    }

    public static function isPaused(string $driver): bool
    {
        return self::pausedUntil($driver) !== null;
    }

    private static function key(string $driver, string $period): string
    {
        return "translate.{$driver}." . ($period === 'month' ? now()->format('Y-m') : now()->format('Y-m-d'));
    }

    private static function pausedKey(string $driver): string
    {
        return "translate.{$driver}.paused";
    }
}
