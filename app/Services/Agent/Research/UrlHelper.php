<?php

namespace App\Services\Agent\Research;

class UrlHelper
{
    public static function host(?string $url): ?string
    {
        $h = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $h ? preg_replace('/^www\./', '', strtolower($h)) : null;
    }

    public static function sameSite(string $a, string $b): bool
    {
        return self::host($a) !== null && self::host($a) === self::host($b);
    }

    /** Resolve an href against a base URL. Returns null for mailto:, tel:, javascript:, anchors, etc. */
    public static function resolve(string $base, string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || $href[0] === '#' || preg_match('#^(mailto|tel|javascript|data|sms|whatsapp):#i', $href)) {
            return null;
        }
        $href = preg_replace('/#.*$/', '', $href);
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $b = parse_url($base);
        if (! $b || ! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');

        if (str_starts_with($href, '//')) {
            return $b['scheme'].':'.$href;
        }
        if ($href[0] === '/') {
            return $origin.self::normalizePath($href);
        }
        if ($href[0] === '?') {
            return $origin.($b['path'] ?? '/').$href;
        }
        $dir = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';

        return $origin.self::normalizePath($dir.$href);
    }

    private static function normalizePath(string $path): string
    {
        $query = '';
        if (($q = strpos($path, '?')) !== false) {
            $query = substr($path, $q);
            $path = substr($path, 0, $q);
        }
        $out = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($out);
            } elseif ($seg !== '.') {
                $out[] = $seg;
            }
        }
        $joined = implode('/', $out);

        return ($joined === '' ? '/' : $joined).$query;
    }
}
