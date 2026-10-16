<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Evidence;
use App\Models\Opportunity;
use App\Models\Service;
use App\Services\Research\ResearchAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchRunTest extends TestCase
{
    use RefreshDatabase;

    // A literal public IP avoids DNS in tests (the fetcher refuses non-public hosts).
    private string $site = 'http://93.184.216.34';

    private function setUpRun(): AgentRun
    {
        config([
            'ai.providers.groq.key' => 'k', 'ai.providers.gemini.key' => null, 'ai.providers.deepseek.key' => null,
            'search.providers.tavily.key' => 't', 'search.providers.serper.key' => null, 'search.providers.brave.key' => null,
        ]);
        $service = Service::create(['name' => 'Backend', 'slug' => 'backend', 'skills_required' => ['Laravel'], 'search_keywords' => ['Laravel developer']]);
        $company = Company::create(['name' => 'Acme Logistics', 'website_url' => $this->site, 'industry' => 'Logistics']);
        $o = Opportunity::create(['company_id' => $company->id, 'title' => 'Dispatch platform', 'type' => 'inferred_need', 'status' => 'discovered', 'source_hash' => 'x']);

        return AgentRun::create(['opportunity_id' => $o->id, 'company_id' => $company->id, 'kind' => 'research', 'status' => 'pending', 'step_limit' => 12]);
    }

    private function fakeWorld(array $ai): void
    {
        $html = '<html><head><title>Acme Logistics</title></head><body><h1>Acme</h1><p>We are hiring a Laravel developer for our dispatch platform.</p>'
            .'<a href="mailto:hello@acme.test">Email us</a><a href="/careers">Careers</a></body></html>';
        Http::fake([
            '93.184.216.34/robots.txt' => Http::response('', 404),
            '93.184.216.34*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
            'api.tavily.com/*' => Http::response(['results' => [['title' => 'Acme raises seed', 'url' => 'https://news.test/acme', 'content' => 'Acme Logistics raised a seed round to expand dispatch.']]]),
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($ai)]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]]),
        ]);
    }

    public function test_run_saves_verified_evidence_and_only_published_contacts(): void
    {
        $run = $this->setUpRun();
        $this->fakeWorld([
            'evidence' => [
                ['source' => 'S1', 'kind' => 'job_listing', 'finding' => 'Hiring a Laravel developer', 'establishes' => 'An open role', 'inference' => 'May want contractors', 'confidence' => 'high', 'excerpt' => 'hiring a Laravel developer for our dispatch platform'],
                ['source' => 'S9', 'finding' => 'Invented source'],
            ],
            'business_need' => 'Open Laravel role.', 'confidence' => 'moderate',
            'scores' => ['need_evidence' => ['score' => 70, 'reasoning' => 'open role'], 'skills_match' => ['score' => 80, 'reasoning' => 'Laravel']],
        ]);

        app(ResearchAgent::class)->run($run);

        $run->refresh();
        $this->assertSame('completed', $run->status, (string) $run->error);
        $this->assertSame(1, Evidence::count());
        $e = Evidence::first();
        $this->assertSame('agent', $e->created_by);
        $this->assertSame($this->site, $e->source_url);
        $this->assertNotNull($e->excerpt);

        $contact = Contact::where('type', 'email')->first();
        $this->assertSame('hello@acme.test', $contact->value);
        $this->assertSame($this->site, $contact->source_url);
        $this->assertSame(0, Contact::where('type', 'phone')->count());
        $this->assertGreaterThanOrEqual(5, $run->steps()->count());
    }

    public function test_invented_quote_is_dropped_and_confidence_lowered(): void
    {
        $run = $this->setUpRun();
        $this->fakeWorld(['evidence' => [['source' => 'S1', 'finding' => 'Big funding', 'confidence' => 'high', 'excerpt' => 'raised fifty million dollars last week']]]);

        app(ResearchAgent::class)->run($run);

        $e = Evidence::first();
        $this->assertNull($e->excerpt);
        $this->assertSame('moderate', $e->confidence);
    }

    public function test_human_entered_fields_are_not_overwritten(): void
    {
        $run = $this->setUpRun();
        $run->opportunity->update(['business_need' => 'My own notes', 'recommended_action' => 'Call first']);
        $this->fakeWorld(['evidence' => [], 'business_need' => 'AI notes', 'recommended_action' => 'AI action']);

        app(ResearchAgent::class)->run($run);

        $o = $run->opportunity->fresh();
        $this->assertSame('My own notes', $o->business_need);
        $this->assertSame('Call first', $o->recommended_action);
    }

    public function test_run_fails_cleanly_when_no_ai_provider_works(): void
    {
        $run = $this->setUpRun();
        Http::fake([
            '93.184.216.34/robots.txt' => Http::response('', 404),
            '93.184.216.34*' => Http::response('<html><body>Acme</body></html>', 200, ['Content-Type' => 'text/html']),
            'api.tavily.com/*' => Http::response(['results' => []]),
            'api.groq.com/*' => Http::response(['error' => ['message' => 'down']], 503),
        ]);

        app(ResearchAgent::class)->run($run);

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('groq', (string) $run->error);
    }
}
