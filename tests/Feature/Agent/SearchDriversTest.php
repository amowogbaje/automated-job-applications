<?php

namespace Tests\Feature\Agent;

use App\Services\Agent\Search\ExaProvider;
use App\Services\Agent\Search\FirecrawlProvider;
use App\Services\Agent\Search\JinaProvider;
use App\Services\Agent\Search\SearxngProvider;
use App\Services\Agent\Search\SerpApiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchDriversTest extends TestCase
{
    public function test_each_driver_normalises_results(): void
    {
        Http::fake([
            'api.exa.ai/*' => Http::response(['results' => [['title' => 'A', 'url' => 'https://a.test', 'text' => 'alpha']]]),
            'serpapi.com/*' => Http::response(['organic_results' => [['title' => 'B', 'link' => 'https://b.test', 'snippet' => 'beta']]]),
            'api.firecrawl.dev/*' => Http::response(['data' => [['title' => 'C', 'url' => 'https://c.test', 'description' => 'gamma']]]),
            's.jina.ai/*' => Http::response(['data' => [['title' => 'D', 'url' => 'https://d.test', 'description' => 'delta']]]),
            'search.example.com/*' => Http::response(['results' => [['title' => 'E', 'url' => 'https://e.test', 'content' => 'eps']]]),
        ]);

        $cases = [
            [new ExaProvider('k'), 'https://a.test', 'alpha'],
            [new SerpApiProvider('k'), 'https://b.test', 'beta'],
            [new FirecrawlProvider('k'), 'https://c.test', 'gamma'],
            [new JinaProvider('k'), 'https://d.test', 'delta'],
            [new SearxngProvider('https://search.example.com/'), 'https://e.test', 'eps'],
        ];
        foreach ($cases as [$p, $url, $snippet]) {
            $r = $p->search('q', 3);
            $this->assertSame($url, $r[0]['url']);
            $this->assertSame($snippet, $r[0]['snippet']);
        }
    }

    public function test_recency_is_sent_to_providers_that_support_it(): void
    {
        Http::fake(['google.serper.dev/*' => Http::response(['organic' => []])]);

        (new \App\Services\Agent\Search\SerperProvider('k'))->search('laravel developer', 5, 30);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'serper') && $r['tbs'] === 'qdr:m');
    }

    public function test_results_dated_outside_the_window_are_dropped_and_preferred_provider_goes_first(): void
    {
        config(['search.providers.tavily.key' => null, 'search.providers.serper.key' => 's', 'search.providers.exa.key' => 'e']);
        Http::fake([
            'api.exa.ai/*' => Http::response(['results' => [
                ['title' => 'Old', 'url' => 'https://old.test', 'text' => '', 'publishedDate' => now()->subDays(90)->toIso8601String()],
                ['title' => 'Fresh', 'url' => 'https://fresh.test', 'text' => '', 'publishedDate' => now()->subDay()->toIso8601String()],
                ['title' => 'Undated', 'url' => 'https://undated.test', 'text' => '', 'publishedDate' => null],
            ]]),
            'google.serper.dev/*' => Http::response(['organic' => [['title' => 'x', 'link' => 'https://x.test', 'snippet' => '']]]),
        ]);

        $r = app(\App\Services\Agent\Search\SearchManager::class)->search('barber shops Lagos', 5, 30, 'exa');

        $this->assertSame('exa', $r['provider']); // serper ranks higher but exa was preferred
        $this->assertSame(['https://fresh.test', 'https://undated.test'], array_column($r['results'], 'url'));
    }
}
