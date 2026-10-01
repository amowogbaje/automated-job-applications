<?php

namespace App\Services\JobSources;

use App\Models\CareerProfile;

/**
 * Which company boards to read. "vendor:slug" strings from ATS_BOARDS in .env
 * plus every account's own list from /profile, deduped.
 */
class AtsBoardRegistry
{
    /** @return array<string, string[]> vendor => [slug, ...] */
    public static function boards(): array
    {
        $entries = config('ats.boards', []);

        try {
            foreach (CareerProfile::whereNotNull('ats_boards')->pluck('ats_boards') as $list) {
                $entries = array_merge($entries, (array) $list);
            }
        } catch (\Throwable) {
            // table not migrated yet — env boards only
        }

        $out = [];
        foreach ($entries as $entry) {
            if (! is_string($entry) || ! str_contains($entry, ':')) {
                continue;
            }
            [$vendor, $slug] = array_map('trim', explode(':', $entry, 2));
            $vendor = strtolower($vendor);
            $slug = strtolower($slug);
            if ($vendor !== '' && $slug !== '') {
                $out[$vendor][$slug] = $slug;
            }
        }

        return array_map('array_values', $out);
    }

    /** "board-slug:key,other:key" env string -> [slug => key] */
    public static function keys(string $configKey): array
    {
        $out = [];
        foreach (explode(',', (string) config("ats.{$configKey}", '')) as $pair) {
            if (str_contains($pair, ':')) {
                [$slug, $key] = array_map('trim', explode(':', $pair, 2));
                if ($slug !== '' && $key !== '') {
                    $out[strtolower($slug)] = $key;
                }
            }
        }

        return $out;
    }
}
