<?php

namespace App\Services\Agent\Push;

/**
 * VAPID (RFC 8292) with plain OpenSSL: no composer package needed.
 * We send "empty" pushes (no encrypted payload); the service worker then asks the app what to show.
 */
class Vapid
{
    public static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** @return array{private: string, public: string} PEM private key and base64url uncompressed public point */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $key) {
            throw new \RuntimeException('OpenSSL could not create an EC key.');
        }
        openssl_pkey_export($key, $pem);
        $ec = openssl_pkey_get_details($key)['ec'];

        return ['private' => $pem, 'public' => self::b64("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))];
    }

    /** ES256 JWT for the push service that owns $endpoint. */
    public static function jwt(string $endpoint, string $subject, string $privatePem, ?int $now = null): string
    {
        $p = parse_url($endpoint);
        $aud = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');
        $head = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64(json_encode(['aud' => $aud, 'exp' => ($now ?? time()) + 12 * 3600, 'sub' => $subject]));
        openssl_sign("{$head}.{$claims}", $der, $privatePem, OPENSSL_ALGO_SHA256);

        return "{$head}.{$claims}.".self::b64(self::derToRaw($der));
    }

    /** ECDSA DER signature to the 64-byte R||S form JWT wants. */
    public static function derToRaw(string $der): string
    {
        $o = 3; // 0x30 len 0x02 ...
        if (ord($der[1]) & 0x80) {
            $o += ord($der[1]) & 0x7f;
        }
        $rl = ord($der[$o]);
        $r = substr($der, $o + 1, $rl);
        $o += 1 + $rl + 1;
        $sl = ord($der[$o]);
        $s = substr($der, $o + 1, $sl);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }
}
