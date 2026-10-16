<?php

namespace App\Services\Research;

/** Refuse to fetch anything that is not a public http(s) site (no localhost, private ranges, odd ports). */
class UrlGuard
{
    public static function isPublicHttpUrl(string $url): bool
    {
        $p = parse_url($url);
        if (! $p || ! isset($p['scheme'], $p['host'])) {
            return false;
        }
        if (! in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            return false;
        }
        if (isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true)) {
            return false;
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return false;
        }

        $host = strtolower(trim($p['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
