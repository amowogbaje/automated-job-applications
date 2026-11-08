<?php

namespace Tests\Unit\Agent;

use App\Services\Agent\Push\Vapid;
use PHPUnit\Framework\TestCase;

class VapidTest extends TestCase
{
    public function testPublicKeyIsUncompressedP256Point(): void
    {
        $k = Vapid::generate();
        $raw = base64_decode(strtr($k['public'], '-_', '+/').'=');
        $this->assertSame(65, strlen($raw));
        $this->assertSame("\x04", $raw[0]);
    }

    public function testJwtSignatureVerifies(): void
    {
        $k = Vapid::generate();
        for ($i = 0; $i < 20; $i++) { // R/S lengths vary, so try several signatures
            $jwt = Vapid::jwt('https://fcm.googleapis.com/fcm/send/abc', 'mailto:me@example.com', $k['private'], 1800000000);
            [$h, $c, $sig] = explode('.', $jwt);
            $raw = base64_decode(strtr($sig, '-_', '+/').'==');
            $this->assertSame(64, strlen($raw));

            $enc = fn (string $n) => (ord($n[0]) & 0x80 ? "\0" : '').$n;
            $r = $enc(ltrim(substr($raw, 0, 32), "\0") ?: "\0");
            $s = $enc(ltrim(substr($raw, 32), "\0") ?: "\0");
            $body = "\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
            $der = "\x30".chr(strlen($body)).$body;
            $pub = openssl_pkey_get_public(openssl_pkey_get_details(openssl_pkey_get_private($k['private']))['key']);
            $this->assertSame(1, openssl_verify("{$h}.{$c}", $der, $pub, OPENSSL_ALGO_SHA256));
        }
        $claims = json_decode(base64_decode(strtr($c, '-_', '+/')), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertSame(1800043200, $claims['exp']);
    }
}
