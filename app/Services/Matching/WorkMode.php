<?php

namespace App\Services\Matching;

/**
 * Remote / hybrid / onsite, as far as a posting says. Pure PHP.
 * A posting that never says "remote" or "hybrid" is treated as onsite (the normal default), so
 * choosing "remote only" never lets an unmarked office job through.
 */
class WorkMode
{
    public const ALL = ['remote', 'hybrid', 'onsite'];

    public static function detect(string $title, ?string $description, ?string $location = null, bool $isRemote = false): string
    {
        $head = mb_strtolower($title.' '.$location);
        $text = mb_strtolower($head.' '.mb_substr((string) $description, 0, 4000));

        if (preg_match('/\bhybrid\b|\b\d\s*days?\s*(?:a|per|\/)\s*week\s*(?:in|on)[\s-]*(?:the\s*)?(?:office|site)\b|\bpartially\s+remote\b|\bflexible\s+(?:remote|office)\b/', $text)) {
            return 'hybrid';
        }
        if ($isRemote || preg_match('/\bremote\b|\bwork\s+from\s+home\b|\bwfh\b|\banywhere\b|\bdistributed\s+team\b/', $head)) {
            return 'remote';
        }
        if (preg_match('/\b(?:fully|100%|100\s*percent|completely)\s+remote\b|\bremote[\s-]+(?:first|friendly|position|role|job|work)\b|\bwork\s+(?:remotely|from\s+anywhere)\b/', $text)) {
            return 'remote';
        }
        if (preg_match('/\bon[\s-]?site\b|\bin[\s-]office\b|\bin[\s-]person\b/', $text)) {
            return 'onsite';
        }

        return 'onsite';
    }

    /**
     * Does a posting fit what the person wants?
     *
     * @param  string[]  $types      allowed modes; empty or all three = no preference
     * @param  string[]  $locations  places for onsite/hybrid work (city or country words); empty = anywhere
     * @return string|null  null when it fits, else the reason it doesn't
     */
    public static function rejects(string $mode, array $types, array $locations, string $location, string $text): ?string
    {
        $types = array_values(array_intersect(self::ALL, array_map('strtolower', $types)));
        if ($types && count($types) < 3 && ! in_array($mode, $types, true)) {
            return "work type ({$mode})";
        }
        $locations = array_values(array_filter(array_map(fn ($l) => mb_strtolower(trim((string) $l)), $locations)));
        if ($locations && $mode !== 'remote') {
            $hay = mb_strtolower($location.' '.mb_substr($text, 0, 1500));
            foreach ($locations as $l) {
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($l, '/').'(?![\p{L}\p{N}])/u', $hay)) {
                    return null;
                }
            }

            return 'location';
        }

        return null;
    }
}
