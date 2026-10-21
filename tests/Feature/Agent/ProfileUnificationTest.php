<?php

namespace Tests\Feature\Agent;

use App\Models\CvVersion;
use App\Models\Opportunity;
use App\Models\PortfolioProject;
use App\Models\Resume;
use App\Models\ResumeProject;
use App\Models\User;
use App\Services\Agent\Profile\OpportunityResumeService;
use App\Services\Agent\Profile\ProfileSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUnificationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->create(); // first account = agent owner
        $resume = Resume::create(['user_id' => $user->id, 'full_name' => 'Florence', 'headline' => 'Laravel dev', 'is_active' => true, 'source' => 'manual']);
        $resume->skills()->create(['category' => 'backend', 'name' => 'Laravel']);

        return [$user, $resume];
    }

    public function test_profile_projects_appear_once_in_agent_projects(): void
    {
        [$user, $resume] = $this->owner();
        $resume->projects()->create(['name' => 'Storyverse', 'description' => 'Reader', 'tech_stack' => ['Laravel']]);
        PortfolioProject::create(['name' => 'storyverse', 'slug' => 'storyverse']); // hand-made twin is adopted, not duplicated

        $this->actingAs($user)->get(route('agent.projects.index'))->assertOk()->assertSee('from Profile');
        $this->assertSame(1, PortfolioProject::count());
        $this->assertNotNull(PortfolioProject::first()->resume_project_id);

        // editing in the Profile flows through; deleting it there unlinks (keeps the agent-side record)
        $resume->projects()->first()->update(['description' => 'New text']);
        $this->assertSame('New text', PortfolioProject::first()->summary);
    }

    public function test_linked_project_core_fields_cannot_be_changed_on_the_agent_side(): void
    {
        [$user, $resume] = $this->owner();
        $resume->projects()->create(['name' => 'Storyverse', 'tech_stack' => ['Laravel']]);
        $p = PortfolioProject::first();

        $this->actingAs($user)->put(route('agent.projects.update', $p), ['name' => 'Hacked', 'proof_points' => '10k readers'])->assertRedirect();
        $this->assertSame('Storyverse', $p->fresh()->name);
        $this->assertSame('10k readers', $p->fresh()->proof_points);
    }

    public function test_tailored_resume_is_built_and_saved_as_the_recommended_cv(): void
    {
        [$user, $resume] = $this->owner();
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 500)]); // AI down: the rules fallback must still work
        $c = \App\Models\Company::create(['name' => 'Acme']);
        $o = Opportunity::create(['company_id' => $c->id, 'title' => 'Laravel API build', 'type' => 'project', 'status' => 'discovered', 'source_hash' => 'x']);

        $svc = app(OpportunityResumeService::class);
        $this->assertTrue($svc->available());
        $cv = $svc->generate($o);

        $this->assertNotNull($cv);
        $this->assertSame($o->id, $cv->opportunity_id);
        $this->assertSame($cv->id, $o->fresh()->recommended_cv_version_id);
        $this->assertSame(1, CvVersion::where('opportunity_id', $o->id)->count());
        $this->assertSame($cv->id, $svc->generate($o)->id); // second call reuses it
    }
}
