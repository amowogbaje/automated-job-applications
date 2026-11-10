<?php

namespace Tests\Unit\Agent;

use App\Services\Agent\Mail\GoogleMail;
use App\Services\Agent\Mail\SendNow;
use PHPUnit\Framework\TestCase;

class SendNowTest extends TestCase
{
    private const SMTP = 'amowogbajegideon@gmail.com';

    public function testSwitchedOffBlocksEveryone(): void
    {
        $d = SendNow::decide(false, self::SMTP, self::SMTP, true, true);
        $this->assertFalse($d['can']);
        $this->assertSame('off', $d['mode']);
    }

    public function testMailboxOwnerUsesAppMail(): void
    {
        $d = SendNow::decide(true, self::SMTP, 'AmowogbajeGideon@gmail.com', false, false);
        $this->assertTrue($d['can']);
        $this->assertSame('smtp', $d['mode']);
    }

    public function testEveryoneElseNeedsGoogle(): void
    {
        $other = 'amowogbajeflorence@gmail.com';
        $this->assertSame('connect', SendNow::decide(true, self::SMTP, $other, false, true)['mode']);
        $this->assertSame('unconfigured', SendNow::decide(true, self::SMTP, $other, false, false)['mode']);
        $ok = SendNow::decide(true, self::SMTP, $other, true, true);
        $this->assertTrue($ok['can']);
        $this->assertSame('google', $ok['mode']);
    }

    public function testEmptyMailboxOwnerMeansNobodyUsesAppMail(): void
    {
        $this->assertSame('connect', SendNow::decide(true, '', self::SMTP, false, true)['mode']);
    }

    public function testEmailFromIdToken(): void
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['email' => 'a@b.test'])), '+/', '-_'), '=');
        $this->assertSame('a@b.test', GoogleMail::emailFromIdToken("x.{$payload}.y"));
        $this->assertNull(GoogleMail::emailFromIdToken('garbage'));
    }
}
