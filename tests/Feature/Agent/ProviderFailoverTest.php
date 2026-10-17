<?php

namespace Tests\Feature;

use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Providers\AllProvidersFailed;
use App\Services\Agent\Search\SearchManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderFailoverTest extends TestCase
{
    use RefreshDatabase;

    private function ok(string $text = 'hello'): array
    {
        return ['choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2]];
    }

    public function test_ai_falls_back_when_first_provider_is_rate_limited_and_skips_it_afterwards(): void
    {
        config(['ai.providers.gemini.key' => 'k1', 'ai.providers.groq.key' => 'k2', 'ai.providers.deepseek.key' => null]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429, ['Retry-After' => '30']),
            'api.groq.com/*' => Http::response($this->ok()),
        ]);

        $ai = app(AiManager::class);
        $this->assertSame('groq', $ai->complete([['role' => 'user', 'content' => 'hi']])->provider);
        $this->assertSame('groq', $ai->complete([['role' => 'user', 'content' => 'hi again']])->provider);

        // Gemini was tried once, then rested
        $this->assertCount(1, Http::recorded(fn ($req) => str_contains($req->url(), 'generativelanguage')));
    }

    public function test_invalid_json_moves_to_next_provider(): void
    {
        config(['ai.providers.gemini.key' => 'k1', 'ai.providers.groq.key' => 'k2', 'ai.providers.deepseek.key' => null]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->ok('Sorry, I cannot do that.')),
            'api.groq.com/*' => Http::response($this->ok('{"a": 1}')),
        ]);

        $r = app(AiManager::class)->complete([['role' => 'user', 'content' => 'json please']], json: true);
        $this->assertSame('groq', $r->provider);
        $this->assertSame(['a' => 1], $r->data);
    }

    public function test_all_ai_providers_failing_gives_one_clear_error(): void
    {
        config(['ai.providers.gemini.key' => 'k1', 'ai.providers.groq.key' => 'k2', 'ai.providers.deepseek.key' => null]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'down']], 503)]);

        $this->expectException(AllProvidersFailed::class);
        app(AiManager::class)->complete([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_search_falls_back_from_tavily_to_serper(): void
    {
        config(['search.providers.tavily.key' => 't', 'search.providers.serper.key' => 's', 'search.providers.brave.key' => null]);
        Http::fake([
            'api.tavily.com/*' => Http::response(['detail' => ['error' => 'plan limit']], 432),
            'google.serper.dev/*' => Http::response(['organic' => [['title' => 'Acme', 'link' => 'https://acme.com', 'snippet' => 'Acme builds logistics software']]]),
        ]);

        $r = app(SearchManager::class)->search('acme');
        $this->assertSame('serper', $r['provider']);
        $this->assertSame('https://acme.com', $r['results'][0]['url']);
    }
}
