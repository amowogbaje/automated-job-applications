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
}
