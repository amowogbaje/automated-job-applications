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
}
