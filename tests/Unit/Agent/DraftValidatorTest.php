<?php

namespace Tests\Unit;

use App\Services\Agent\Outreach\DraftValidator;
use App\Services\Agent\Outreach\OutreachChannels;
use PHPUnit\Framework\TestCase;

class DraftValidatorTest extends TestCase
{
    private function ctx(array $over = []): array
    {
        return $over + [
            'channel' => 'email', 'evidence_ids' => [7, 9], 'has_evidence' => true,
            'projects' => [3 => 'RouteIQ'], 'allowed_hosts' => ['acme.com', 'florence.dev'],
            'allowed_contacts' => ['hello@acme.com', '+2348012345678'],
        ];
    }

    private function good(): array
    {
        return ['drafts' => [
            ['approach' => 'direct', 'subject' => 'Backend help for Acme', 'alt_subjects' => ['Laravel developer available'],
                'body' => 'Hello Acme team, I am a Laravel developer who builds dispatch and booking systems for small logistics companies. If extra hands on your platform would help this quarter, I would be glad to share a short overview of how I work.',
                'claims' => [], 'project_id' => null],
            ['approach' => 'problem_solving', 'subject' => 'Your open backend role', 'alt_subjects' => [],
                'body' => 'Hi, I saw that Acme is hiring a backend engineer for its dispatch platform. Teams in that position often need someone who can take on integrations quickly while the permanent hire is found. Would a contractor for a few weeks be useful?',
                'claims' => [['quote' => 'Acme is hiring a backend engineer for its dispatch platform', 'evidence_id' => 7]], 'project_id' => null],
            ['approach' => 'experience_led', 'subject' => 'RouteIQ and what it might mean for you', 'alt_subjects' => [],
                'body' => 'Last year I delivered RouteIQ, a fleet tracking tool that cut manual scheduling time for its operators. Several of its lessons carry over to fast moving courier operations. May I send two screenshots?',
                'claims' => [], 'project_id' => 3],
        ]];
    }

    private function with(callable $f, ?array $ctx = null): array
    {
        $d = $this->good();
        $f($d);

        return DraftValidator::check($d, $ctx ?? $this->ctx());
    }

    public function test_a_good_set_passes_and_keeps_only_verified_claims(): void
    {
        $r = DraftValidator::check($this->good(), $this->ctx());
        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertSame([7], $r['drafts']['problem_solving']['evidence_ids']);
        $this->assertSame(3, $r['drafts']['experience_led']['project_id']);
    }

    public function test_missing_approach_fails(): void
    {
        $r = $this->with(function (&$d) { array_pop($d['drafts']); });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('experience_led', $r['error']);
    }

    public function test_invented_link_is_rejected_but_provided_hosts_pass(): void
    {
        $r = $this->with(function (&$d) { $d['drafts'][0]['body'] .= ' See https://sketchy-site.io/me for more.'; });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('sketchy-site.io', $r['error']);

        $ok = $this->with(function (&$d) { $d['drafts'][0]['body'] .= ' My work: https://www.florence.dev/work'; });
        $this->assertTrue($ok['ok'], (string) $ok['error']);
    }

    public function test_invented_email_or_phone_is_rejected(): void
    {
        $r = $this->with(function (&$d) { $d['drafts'][0]['body'] .= ' Write to ceo@acme.com directly.'; });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('email', $r['error']);

        $r = $this->with(function (&$d) { $d['drafts'][0]['body'] .= ' Call me on +44 7700 900123.'; });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('phone', $r['error']);

        $ok = $this->with(function (&$d) { $d['drafts'][0]['body'] .= ' Or reach you at hello@acme.com.'; });
        $this->assertTrue($ok['ok'], (string) $ok['error']);
    }

    public function test_problem_solving_must_cite_evidence_when_evidence_exists(): void
    {
        $r = $this->with(function (&$d) { $d['drafts'][1]['claims'] = []; });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('evidence', $r['error']);
    }

    public function test_claim_with_unknown_evidence_or_quote_not_in_body_is_dropped(): void
    {
        $r = $this->with(function (&$d) {
            $d['drafts'][1]['claims'][] = ['quote' => 'Acme raised a huge round', 'evidence_id' => 9];  // not in body
            $d['drafts'][1]['claims'][] = ['quote' => 'Acme is hiring a backend engineer', 'evidence_id' => 999]; // unknown id
        });
        $this->assertTrue($r['ok']);
        $this->assertSame([7], $r['drafts']['problem_solving']['evidence_ids']);
        $this->assertCount(1, $r['drafts']['problem_solving']['claims']);
    }

    public function test_without_any_evidence_problem_solving_is_allowed_but_warned(): void
    {
        $ctx = $this->ctx(['evidence_ids' => [], 'has_evidence' => false]);
        $r = $this->with(function (&$d) { $d['drafts'][1]['claims'] = []; }, $ctx);
        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertTrue(count($r["drafts"]["problem_solving"]["warnings"]) > 0);
    }

    public function test_experience_led_must_name_a_provided_project(): void
    {
        $r = $this->with(function (&$d) { $d['drafts'][2]['project_id'] = 42; });
        $this->assertFalse($r['ok']);

        $r = $this->with(function (&$d) { $d['drafts'][2]['body'] = str_replace('RouteIQ', 'a tracking tool', $d['drafts'][2]['body']); });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('RouteIQ', $r['error']);
    }

    public function test_length_limit_per_channel(): void
    {
        $long = str_repeat('Delivering reliable software for logistics teams matters to me. ', 40);
        $r = $this->with(function (&$d) use ($long) { $d['drafts'][0]['body'] = $long; });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('too long', $r['error']);
    }

    public function test_near_duplicate_drafts_are_rejected(): void
    {
        $r = $this->with(function (&$d) { $d['drafts'][2]['body'] = $d['drafts'][0]['body'].' RouteIQ'; $d['drafts'][2]['project_id'] = 3; });
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('similar', $r['error']);
    }

    public function test_placeholders_guarantees_and_generic_openers_are_rejected(): void
    {
        foreach (['Dear [Name], ', 'I guarantee results. ', 'I hope this email finds you well. '] as $bad) {
            $r = $this->with(function (&$d) use ($bad) { $d['drafts'][0]['body'] = $bad.$d['drafts'][0]['body']; });
            $this->assertFalse($r['ok'], $bad);
        }
    }

    public function test_email_needs_a_subject_but_whatsapp_does_not(): void
    {
        $r = $this->with(function (&$d) { $d['drafts'][0]['subject'] = ''; });
        $this->assertFalse($r['ok']);

        $ctx = $this->ctx(['channel' => 'whatsapp']);
        $r = $this->with(function (&$d) { foreach ($d['drafts'] as &$x) { unset($x['subject']); } }, $ctx);
        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertNull($r['drafts']['direct']['subject']);
    }

    public function test_channel_mapping(): void
    {
        $this->assertSame('email', OutreachChannels::forContactType('email'));
        $this->assertSame('contact_form', OutreachChannels::forContactType('professional_profile'));
        $this->assertSame('application', OutreachChannels::forContactType('careers_portal'));
        $this->assertSame('phone', OutreachChannels::forContactType('phone'));
        $this->assertNull(OutreachChannels::forContactType('other'));
    }
}
