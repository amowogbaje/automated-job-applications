<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\Evidence;
use App\Models\Opportunity;
use App\Models\Service;
use App\Models\User;
use App\Services\Agent\Discovery\DiscoveryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoveryRunTest extends TestCase
{
    use RefreshDatabase;

    private function world(array $candidates, array $searchResults = null): DiscoveryRun
    {
        config([
            'ai.providers.groq.key' => 'k', 'ai.providers.gemini.key' => null, 'ai.providers.deepseek.key' => null,
            'search.providers.tavily.key' => 't', 'search.providers.serper.key' => null, 'search.providers.brave.key' => null,
            'discovery.max_queries_per_run' => 1,
        ]);
        $service = Service::create(['name' => 'Backend', 'slug' => 'backend', 'skills_required' => ['Laravel'], 'search_keywords' => ['Laravel developer'], 'geographic_preferences' => ['UK']]);

        Http::fake([
            'api.tavily.com/*' => Http::response(['results' => $searchResults ?? [
                ['title' => 'Acme Logistics hiring', 'url' => 'https://jobs.example/acme', 'content' => 'Acme Logistics is hiring a Laravel developer to rebuild its dispatch platform.'],
                ['title' => 'Laravel on Wikipedia', 'url' => 'https://en.wikipedia.org/wiki/Laravel', 'content' => 'Laravel is a PHP framework.'],
            ]]),
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['candidates' => $candidates])]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10]]),
        ]);

        return DiscoveryRun::create(['strategy' => 'hiring', 'service_id' => $service->id, 'status' => 'pending', 'options' => []]);
    }

    private function good(): array
    {
        return ['source' => 'S1', 'company_name' => 'Acme Logistics', 'website_url' => '', 'title' => 'Laravel developer', 'type' => 'job_posting',
            'finding' => 'Hiring a Laravel developer', 'establishes' => 'An open role', 'inference' => 'Might take contractors',
            'excerpt' => 'hiring a Laravel developer to rebuild its dispatch platform', 'confidence' => 'moderate'];
    }

    public function test_run_saves_verified_candidates_as_discovered_with_evidence(): void
    {
        $run = $this->world([$this->good(), ['source' => 'S1', 'company_name' => 'Ghost Ltd', 'finding' => 'x', 'excerpt' => 'invented quote that is not there']]);

        app(DiscoveryEngine::class)->run($run);

        $run->refresh();
        $this->assertSame('completed', $run->status, (string) $run->error);
        $this->assertSame(1, $run->candidates_new);
        $this->assertSame(1, Opportunity::count());
        $o = Opportunity::first();
        $this->assertSame('discovered', $o->status);
        $this->assertSame($run->id, $o->discovery_run_id);
        $this->assertSame('Acme Logistics', $o->company->name);
        $this->assertSame(1, Evidence::where('opportunity_id', $o->id)->where('created_by', 'agent')->count());
        $this->assertNull(Company::where('name', 'Ghost Ltd')->first());

        $trace = $run->result['trace'];
        $this->assertNotEmpty($trace['searches'][0]['results']);
        $this->assertStringContainsString('sent to the AI', $trace['searches'][0]['results'][0]['fate']);
        $verdicts = array_column($trace['ai'][0]['candidates'], 'verdict');
        $this->assertTrue(collect($verdicts)->contains(fn ($v) => str_starts_with($v, 'saved')));
        $this->assertTrue(collect($verdicts)->contains(fn ($v) => str_contains($v, 'quote is not in the source')));
        $this->actingAs(\App\Models\User::factory()->create())->get(route('agent.discovery.trace', $run))->assertOk();
    }

    public function test_second_run_does_not_duplicate_known_urls(): void
    {
        $run = $this->world([$this->good()]);
        app(DiscoveryEngine::class)->run($run);

        $again = DiscoveryRun::create(['strategy' => 'hiring', 'service_id' => $run->service_id, 'status' => 'pending', 'options' => []]);
        app(DiscoveryEngine::class)->run($again);

        $this->assertSame(1, Opportunity::count());
        $this->assertSame('completed', $again->fresh()->status);
        $this->assertSame(0, $again->fresh()->candidates_new);
    }

    public function test_do_not_contact_companies_are_skipped(): void
    {
        $run = $this->world([$this->good()]);
        Company::create(['name' => 'Acme Logistics', 'do_not_contact' => true]);

        app(DiscoveryEngine::class)->run($run);

        $this->assertSame(0, Opportunity::count());
    }

    public function test_discovery_page_and_start_need_providers_and_respect_the_daily_cap(): void
    {
        $user = User::factory()->create(); // first account may use /agent
        $this->actingAs($user)->get(route('agent.discovery.index'))->assertOk()->assertSee('Discovery');

        $run = $this->world([$this->good()]);
        config(['discovery.runs_per_day' => 1, 'research.mode' => 'sync']);
        $this->actingAs($user)->post(route('agent.discovery.store'), ['strategies' => ['hiring', 'funded_growth'], 'service_id' => $run->service_id])
            ->assertSessionHasErrors('discovery');
    }
}
