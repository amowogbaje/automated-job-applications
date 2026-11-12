<?php

namespace App\Services\Agent\Leads;

use Carbon\Carbon;

class LocalTime
{
    /**
     * A <input type="datetime-local"> value is the user's wall-clock time. The browser also sends its
     * offset (JS getTimezoneOffset(): minutes to ADD to local time to get UTC, e.g. -60 for Lagos).
     */
    public static function toApp(string $local, int $offsetMinutes): Carbon
    {
        return Carbon::parse($local, 'UTC')->addMinutes($offsetMinutes)->setTimezone(config('app.timezone', 'UTC'));
    }
}
