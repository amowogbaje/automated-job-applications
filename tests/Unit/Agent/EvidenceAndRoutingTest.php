<?php

namespace Tests\Unit;

use App\Services\Agent\Research\ContactRouting;
use App\Services\Agent\Research\EvidenceVerifier;
use PHPUnit\Framework\TestCase;

class EvidenceAndRoutingTest extends TestCase
{
    private array $sources = [
        'S1' => ['url' => 'https://acme.com/careers', 'title' => 'Careers', 'text' => 'We are hiring a Senior Laravel Developer to join our platform team in Lagos.'],
        'S2' => ['url' => 'https://news.com/acme', 'title' => 'News', 'text' => 'Acme raised a seed round.'],
    ];

    private array $kinds = ['website', 'job_listing', 'news', 'other'];

    public function testExcerptVerification(): void
    {
        $this->assertTrue(EvidenceVerifier::containsExcerpt($this->sources['S1']['text'], 'hiring a senior LARAVEL developer'));
        $this->assertFalse(EvidenceVerifier::containsExcerpt($this->sources['S1']['text'], 'hiring a Python developer'));
        $this->assertFalse(EvidenceVerifier::containsExcerpt($this->sources['S1']['text'], 'hi'));
    }

    public function testValidEvidenceKept(): void
    {
        $rows = EvidenceVerifier::filter([[
            'source' => 'S1', 'kind' => 'job_listing', 'finding' => 'Hiring a Laravel dev', 'establishes' => 'They have an open role',
            'inference' => 'May need contractors', 'confidence' => 'high', 'excerpt' => 'hiring a Senior Laravel Developer',
        ]], $this->sources, $this->kinds);
        $this->assertCount(1, $rows);
        $this->assertSame('https://acme.com/careers', $rows[0]['source_url']);
        $this->assertSame('high', $rows[0]['confidence']);
        $this->assertSame('hiring a Senior Laravel Developer', $rows[0]['excerpt']);
    }

    public function testFabricatedSourceIsDropped(): void
    {
        $rows = EvidenceVerifier::filter([
            ['source' => 'S9', 'finding' => 'Made up'],
            ['source' => 'S1', 'finding' => ''],
            'not an array',
        ], $this->sources, $this->kinds);
        $this->assertCount(0, $rows);
    }

    public function testInventedQuoteDropsExcerptAndLowersConfidence(): void
    {
        $rows = EvidenceVerifier::filter([
            ['source' => 'S2', 'finding' => 'Funding', 'confidence' => 'high', 'excerpt' => 'Acme raised a $50 million Series B'],
            ['source' => 'S2', 'finding' => 'Funding 2', 'confidence' => 'moderate', 'excerpt' => 'completely invented sentence here'],
        ], $this->sources, $this->kinds);
        $this->assertSame('moderate', $rows[0]['confidence']);
        $this->assertNull($rows[0]['excerpt']);
        $this->assertSame('low', $rows[1]['confidence']);
    }

    public function testBadKindAndConfidenceAreNormalised_andCapApplies(): void
    {
        $items = [];
        for ($i = 0; $i < 10; $i++) {
            $items[] = ['source' => 'S1', 'finding' => "f$i", 'kind' => 'weird', 'confidence' => 'certain'];
        }
        $rows = EvidenceVerifier::filter($items, $this->sources, $this->kinds, 6);
        $this->assertCount(6, $rows);
        $this->assertSame('other', $rows[0]['kind']);
        $this->assertSame('moderate', $rows[0]['confidence']);
    }

    public function testContactScoreAndRouting(): void
    {
        $email = [['type' => 'email', 'is_public_business_channel' => true]];
        $this->assertSame(90, ContactRouting::score($email)['score']);
        $this->assertSame('email', ContactRouting::recommend($email, null, null)['channel']);

        $unconfirmed = [['type' => 'email', 'is_public_business_channel' => false]];
        $this->assertSame(54, ContactRouting::score($unconfirmed)['score']);

        $this->assertSame(0, ContactRouting::score([])['score']);
        $this->assertNull(ContactRouting::recommend([], null, null)['channel']);
    }

    public function testJobOpeningPrefersApplicationPortal(): void
    {
        $contacts = [['type' => 'email', 'is_public_business_channel' => true], ['type' => 'careers_portal', 'is_public_business_channel' => true]];
        $this->assertSame('application', ContactRouting::recommend($contacts, 'employment', 'job_posting')['channel']);
        $this->assertSame('email', ContactRouting::recommend($contacts, 'consulting', 'inferred_need')['channel']);
        $this->assertSame(90, ContactRouting::score([['type' => 'careers_portal', 'is_public_business_channel' => true]], true)['score']);
    }

    public function testPhoneOnlyIsNeverTreatedAsWhatsApp(): void
    {
        $r = ContactRouting::recommend([['type' => 'phone']], null, null);
        $this->assertSame('phone', $r['channel']);
        $this->assertStringContainsString('not assumed', $r['rationale']);
    }
}
