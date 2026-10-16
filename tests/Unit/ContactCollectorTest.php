<?php

namespace Tests\Unit;

use App\Services\Research\ContactCollector;
use App\Services\Research\SiteInspector;
use PHPUnit\Framework\TestCase;

class ContactCollectorTest extends TestCase
{
    private function pages(): array
    {
        return [
            ['url' => 'https://acme.com/', 'emails' => [], 'phones' => [], 'whatsapp' => [], 'has_form' => false,
                'links' => [['url' => 'https://acme.com/careers', 'text' => 'Careers'], ['url' => 'https://acme.com/contact', 'text' => 'Contact'], ['url' => 'https://partner.com/jobs', 'text' => 'Partner jobs']]],
            ['url' => 'https://www.acme.com/contact', 'emails' => ['hello@acme.com', 'press@partner.com'], 'phones' => ['+442079460000'], 'whatsapp' => ['+2348012345678'], 'has_form' => true, 'links' => []],
            ['url' => 'https://directory.com/acme', 'emails' => ['acme-listing@directory.com'], 'phones' => [], 'whatsapp' => [], 'has_form' => false, 'links' => []],
        ];
    }

    private function byType(array $c, string $t): array
    {
        return array_values(array_filter($c, fn ($x) => $x['type'] === $t));
    }

    public function testCollectsOnlyPublishedRoutesWithSources(): void
    {
        $c = ContactCollector::collect($this->pages(), 'https://acme.com');
        $emails = $this->byType($c, 'email');
        $this->assertCount(3, $emails);
        $this->assertSame('https://www.acme.com/contact', $emails[0]['source_url']);
        $this->assertTrue($emails[0]['is_public_business_channel']);
        // partner address on our own page: not assumed to be the company's business channel
        $this->assertFalse($emails[1]['is_public_business_channel']);
        // third-party directory: never marked as business channel
        $this->assertFalse($emails[2]['is_public_business_channel']);
    }

    public function testWhatsappPhoneFormAndCareers(): void
    {
        $c = ContactCollector::collect($this->pages(), 'https://acme.com');
        $this->assertSame('+2348012345678', $this->byType($c, 'whatsapp')[0]['value']);
        $this->assertSame('+442079460000', $this->byType($c, 'phone')[0]['value']);
        $this->assertSame('https://www.acme.com/contact', $this->byType($c, 'contact_form')[0]['value']);
        $careers = $this->byType($c, 'careers_portal');
        $this->assertCount(1, $careers);
        $this->assertSame('https://acme.com/careers', $careers[0]['value']);
    }

    public function testEverythingHasASourceUrl(): void
    {
        foreach (ContactCollector::collect($this->pages(), 'https://acme.com') as $c) {
            $this->assertTrue($c['source_url'] !== '' && str_starts_with($c['source_url'], 'http'));
        }
    }

    public function testLimitsAndDedupe(): void
    {
        $emails = array_map(fn ($i) => "u{$i}@acme.com", range(1, 12));
        $pages = [['url' => 'https://acme.com/', 'emails' => array_merge($emails, $emails), 'phones' => [], 'whatsapp' => [], 'has_form' => false, 'links' => []]];
        $this->assertCount(5, $this->byType(ContactCollector::collect($pages, 'https://acme.com'), 'email'));
    }

    public function testPickSubpagesPrefersContactThenCareers(): void
    {
        $links = [
            ['url' => 'https://acme.com/about', 'text' => 'About'],
            ['url' => 'https://acme.com/blog/post', 'text' => 'Blog'],
            ['url' => 'https://acme.com/careers', 'text' => 'Jobs'],
            ['url' => 'https://acme.com/contact-us', 'text' => 'Contact'],
            ['url' => 'https://other.com/contact', 'text' => 'Contact'],
            ['url' => 'https://acme.com/brochure.pdf', 'text' => 'Services brochure'],
        ];
        $this->assertSame(['https://acme.com/contact-us', 'https://acme.com/careers'], SiteInspector::pickSubpages($links, 'https://acme.com/', 2));
        $this->assertSame([], SiteInspector::pickSubpages($links, 'https://acme.com/', 0));
    }
}
