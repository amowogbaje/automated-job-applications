<?php

namespace Tests\Unit\Agent;

use App\Services\Agent\Discovery\CandidateFilter;
use App\Services\Agent\Discovery\Strategies;
use PHPUnit\Framework\TestCase;

class DiscoveryTest extends TestCase
{
    private function sources(): array
    {
        return ['S1' => [
            'url' => 'https://jobs.example/acme-laravel',
            'title' => 'Acme Logistics is hiring a Laravel developer',
            'text' => 'Acme Logistics is hiring a Laravel developer to rebuild its dispatch platform. Contract possible.',
        ]];
    }

    private function item(array $over = []): array
    {
        return $over + [
            'source' => 'S1', 'company_name' => 'Acme Logistics', 'website_url' => 'http://93.184.216.34',
            'title' => 'Laravel developer', 'type' => 'job_posting', 'industry' => 'Logistics', 'country' => 'gb',
            'finding' => 'Hiring a Laravel developer', 'establishes' => 'An open role', 'inference' => 'May accept contractors',
            'excerpt' => 'hiring a Laravel developer to rebuild its dispatch platform', 'confidence' => 'high',
        ];
    }

    public function test_a_verified_candidate_is_accepted_and_normalised(): void
    {
        $r = CandidateFilter::check($this->item(), $this->sources(), 'inferred_need');
        $this->assertTrue($r['ok']);
        $this->assertSame('Acme Logistics', $r['data']['company_name']);
        $this->assertSame('GB', $r['data']['country']);
        $this->assertSame('job_posting', $r['data']['type']);
        $this->assertSame('http://93.184.216.34', $r['data']['website_url']);
    }

    public function test_a_quote_that_is_not_in_the_source_is_dropped(): void
    {
        $r = CandidateFilter::check($this->item(['excerpt' => 'raised a 10 million Series B']), $this->sources(), 'inferred_need');
        $this->assertFalse($r['ok']);
    }

    public function test_missing_quote_or_unknown_source_is_dropped(): void
    {
        $this->assertFalse(CandidateFilter::check($this->item(['excerpt' => '']), $this->sources(), 'inferred_need')['ok']);
        $this->assertFalse(CandidateFilter::check($this->item(['source' => 'S9']), $this->sources(), 'inferred_need')['ok']);
        $this->assertFalse(CandidateFilter::check($this->item(['company_name' => '  ']), $this->sources(), 'inferred_need')['ok']);
    }

    public function test_bad_website_and_odd_values_fall_back_safely(): void
    {
        $r = CandidateFilter::check($this->item(['website_url' => 'http://localhost/admin', 'type' => 'nonsense', 'confidence' => 'certain', 'country' => 'England']), $this->sources(), 'rfp');
        $this->assertTrue($r['ok']);
        $this->assertNull($r['data']['website_url']);
        $this->assertSame('rfp', $r['data']['type']);
        $this->assertSame('low', $r['data']['confidence']);
        $this->assertNull($r['data']['country']);
    }

    public function test_junk_domains(): void
    {
        $this->assertTrue(CandidateFilter::isJunk('https://en.wikipedia.org/wiki/Laravel'));
        $this->assertTrue(CandidateFilter::isJunk('https://www.facebook.com/acme'));
        $this->assertFalse(CandidateFilter::isJunk('https://acme.example/careers'));
    }

    public function test_templates_fill_in_and_skip_missing_placeholders(): void
    {
        $q = Strategies::expand(
            ['{keyword} developer hiring {region}', 'hiring {skill} developer', 'needs {nothing}'],
            ['keyword' => ['Laravel developer', 'API developer'], 'skill' => ['PHP'], 'region' => ['UK']]
        );
        $this->assertContains('Laravel developer developer hiring UK', $q);
        $this->assertContains('API developer developer hiring UK', $q);
        $this->assertContains('hiring PHP developer', $q);
        foreach ($q as $x) {
            $this->assertFalse(str_contains($x, '{'));
        }
    }

    public function test_own_queries_come_first_deduped_and_capped(): void
    {
        $q = Strategies::queries('hiring', null, ['laravel contract uk', 'Laravel Contract UK', '', 'php remote'], 5);
        $this->assertSame(['laravel contract uk', 'php remote'], $q);
        $this->assertCount(1, Strategies::queries('hiring', null, ['a', 'b', 'c'], 1));
    }

    public function test_every_strategy_is_known(): void
    {
        foreach (['technical_need', 'hiring', 'external_assistance', 'funded_growth', 'portfolio_fit'] as $k) {
            $this->assertTrue(Strategies::exists($k));
        }
        $this->assertFalse(Strategies::exists('scrape_linkedin'));
    }

    public function test_work_arrangement_that_does_not_fit_the_service_is_dropped(): void
    {
        $r = CandidateFilter::check($this->item(['work_mode' => 'onsite']), $this->sources(), 'job_posting', ['remote']);
        $this->assertFalse($r['ok']);
        $this->assertTrue(CandidateFilter::check($this->item(['work_mode' => 'onsite']), $this->sources(), 'job_posting', ['remote', 'onsite'])['ok']);
        $this->assertTrue(CandidateFilter::check($this->item(), $this->sources(), 'job_posting', ['remote'])['ok']); // unstated = allowed
    }

    public function test_prospecting_skips_tech_companies_and_forces_the_prospect_type(): void
    {
        $this->assertFalse(CandidateFilter::check($this->item(['is_technology_company' => true]), $this->sources(), 'prospect', [], 'prospecting')['ok']);
        $r = CandidateFilter::check($this->item(['type' => 'job_posting']), $this->sources(), 'prospect', [], 'prospecting');
        $this->assertSame('prospect', $r['data']['type']);
    }

    public function test_only_contacts_written_in_the_source_survive(): void
    {
        $source = ['url' => 'http://93.184.216.34/acme', 'title' => 'Acme Clinic', 'text' => 'Acme Clinic, Lagos. Email info@acmeclinic.ng or call +234 801 234 5678. Book at http://93.184.216.34/acme/book'];
        $c = CandidateFilter::contacts([
            ['type' => 'email', 'value' => 'info@acmeclinic.ng', 'label' => 'General'],
            ['type' => 'email', 'value' => 'ceo@acmeclinic.ng'],            // not in the text
            ['type' => 'phone', 'value' => '+234 801 234 5678'],
            ['type' => 'phone', 'value' => '+234 999 000 1111'],            // not in the text
            ['type' => 'contact_form', 'value' => 'http://93.184.216.34/acme/book'],
            ['type' => 'contact_form', 'value' => 'https://evil.example/form'], // other host
            ['type' => 'email', 'value' => 'noreply@acmeclinic.ng'],
        ], $source);

        $this->assertSame(['email|info@acmeclinic.ng', 'phone|+234 801 234 5678', 'contact_form|http://93.184.216.34/acme/book'],
            array_map(fn ($x) => $x['type'].'|'.$x['value'], $c));
    }

    public function test_prospect_queries_cover_every_target_and_chosen_platform(): void
    {
        $q = Strategies::prospectQueries(['barber shops', 'mechanics', 'hair stylists'], ['Lagos'], ['instagram', 'facebook']);
        $this->assertSame('barber shops Lagos', $q[0]);
        $this->assertSame('mechanics Lagos', $q[1]);
        $this->assertSame('hair stylists Lagos', $q[2]);
        $this->assertContains('mechanics Lagos instagram', $q);
        $this->assertContains('barber shops Lagos facebook page', $q);
        $this->assertContains('hair stylists Lagos contact us', $q);
    }

    public function test_social_pages_are_junk_unless_prospecting(): void
    {
        $this->assertTrue(CandidateFilter::isJunk('https://www.instagram.com/lagos_cuts'));
        $this->assertFalse(CandidateFilter::isJunk('https://www.instagram.com/lagos_cuts', true));
        $this->assertTrue(CandidateFilter::isJunk('https://www.quora.com/x', true));
    }

    public function test_social_only_business_gets_its_page_as_a_contact_and_a_presence_label(): void
    {
        $sources = ['S1' => ['url' => 'https://www.instagram.com/lagos_cuts', 'title' => 'Lagos Cuts Barbershop (@lagos_cuts)', 'text' => 'Lagos Cuts Barbershop. Walk-ins welcome. DM to book.']];
        $item = ['source' => 'S1', 'company_name' => 'Lagos Cuts Barbershop', 'website_url' => '', 'finding' => 'A barbershop in Lagos', 'excerpt' => 'Walk-ins welcome. DM to book.'];
        $r = CandidateFilter::check($item, $sources, 'prospect', [], 'prospecting');

        $this->assertTrue($r['ok']);
        $this->assertSame('social_only', $r['data']['web_presence']);
        $this->assertSame('professional_profile', $r['data']['contacts'][0]['type']);
        $this->assertSame('https://www.instagram.com/lagos_cuts', $r['data']['contacts'][0]['value']);
    }
}
