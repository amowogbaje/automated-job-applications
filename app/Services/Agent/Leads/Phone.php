<?php

namespace App\Services\Agent\Leads;

class Phone
{
    /** Normalises to +<digits>. Returns null when it can't be a phone number. */
    public static function normalize(?string $raw, string $defaultCc = '234'): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $plus = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $d = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $d = substr($d, 2);
        }
        if ($d === '') {
            return null;
        }
        if (! $plus) {
            if (str_starts_with($d, '0')) {
                $d = $defaultCc.substr($d, 1);
            } elseif (strlen($d) <= 10) {
                $d = $defaultCc.$d;
            }
        }

        return strlen($d) >= 8 && strlen($d) <= 15 ? '+'.$d : null;
    }

    public static function waLink(string $phone, ?string $text = null): string
    {
        return 'https://wa.me/'.ltrim($phone, '+').($text ? '?text='.rawurlencode($text) : '');
    }
}
