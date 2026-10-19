<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BriefApiTest extends TestCase
{
    private const URL = '/api/amowogbajewebsite/send-brief';

    private function payload(array $o = []): array
    {
        return array_merge(['name' => 'Test', 'email' => 'you@example.com', 'need' => 'Need a site', 'timeline' => '', 'budget' => '', 'link' => '', 'website' => ''], $o);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('brief');
        config(['mail.default' => 'array', 'brief.to' => 'hello@amowogbaje.com', 'brief.bcc' => 'gid@example.com']);
        Mail::purge('array');
    }

    public function test_sends_and_returns_ok(): void
    {
        $this->postJson(self::URL, $this->payload())->assertOk()->assertExactJson(['ok' => true]);
        $msg = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $msg);
        $raw = $msg[0]->getOriginalMessage();
        $this->assertSame('Project brief from Test', $raw->getSubject());
        $this->assertSame('you@example.com', $raw->getReplyTo()[0]->getAddress());
        $this->assertSame('hello@amowogbaje.com', $raw->getTo()[0]->getAddress());
    }

    public function test_validation_failure_is_422_json(): void
    {
        $this->postJson(self::URL, $this->payload(['name' => '']))->assertStatus(422)->assertJsonPath('ok', false)->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_honeypot_fakes_success_and_sends_nothing(): void
    {
        $this->postJson(self::URL, $this->payload(['website' => 'spam.com']))->assertOk()->assertExactJson(['ok' => true]);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_newlines_in_name_cannot_inject_headers(): void
    {
        $this->postJson(self::URL, $this->payload(['name' => "Bob\r\nBcc: evil@example.com"]))->assertOk();
        $raw = Mail::mailer('array')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        $this->assertCount(1, $raw->getBcc());
    }

    public function test_sixth_request_in_an_hour_is_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::URL, $this->payload())->assertOk();
        }
        $this->postJson(self::URL, $this->payload())->assertStatus(429)->assertExactJson(['ok' => false]);
    }

    public function test_cors_preflight_allows_portfolio_origin(): void
    {
        $this->call('OPTIONS', self::URL, [], [], [], [
            'HTTP_ORIGIN' => 'https://www.amowogbaje.com', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'https://www.amowogbaje.com');
    }
}
