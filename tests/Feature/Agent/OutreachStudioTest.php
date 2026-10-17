<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Evidence;
use App\Models\Opportunity;
use App\Models\OutreachDraft;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutreachStudioTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        config([
            'research.mode' => 'sync',
            'ai.providers.groq.key' => 'k', 'ai.providers.gemini.key' => null, 'ai.providers.deepseek.key' => null,
        ]);
        $user = User::factory()->create();   // first account = allowed to use /agent
        $company = Company::create(['name' => 'Acme Logistics', 'website_url' => 'https://acme.com', 'domain' => 'acme.com']);
        $o = Opportunity::create(['company_id' => $company->id, 'title' => 'Dispatch platform', 'type' => 'inferred_need', 'status' => 'qualified', 'source_hash' => 'h']);
        $ev = Evidence::create(['opportunity_id' => $o->id, 'company_id' => $company->id, 'kind' => 'job_listing', 'source_url' => 'https://acme.com/careers',
            'retrieved_at' => now(), 'finding' => 'Hiring a backend engineer', 'establishes' => 'An open backend role', 'confidence' => 'high']);
        $contact = Contact::create(['company_id' => $company->id, 'opportunity_id' => $o->id, 'type' => 'email', 'value' => 'hello@acme.com',
            'source_url' => 'https://acme.com/contact', 'is_public_business_channel' => true]);
        Signature::create(['name' => 'Default', 'channel' => 'email', 'body' => "Best,\nFlorence", 'is_default' => true]);

        return [$user, $o, $contact, $ev];
    }

    private function fakeAi(int $evidenceId): void
    {
        $drafts = ['drafts' => [
            ['approach' => 'direct', 'subject' => 'Backend help for Acme', 'alt_subjects' => [], 'claims' => [], 'project_id' => null,
                'body' => 'Hello Acme team, I am a Laravel developer who builds dispatch and booking systems for small logistics companies. If extra hands on your platform would help this quarter, I would be glad to share a short overview of how I work.'],
            ['approach' => 'problem_solving', 'subject' => 'Your open backend role', 'alt_subjects' => [], 'project_id' => null,
                'claims' => [['quote' => 'Acme is hiring a backend engineer', 'evidence_id' => $evidenceId]],
                'body' => 'Hi, I saw that Acme is hiring a backend engineer for its dispatch work. Teams in that position often need someone who can take on integrations quickly while the permanent hire is found. Would a contractor for a few weeks be useful?'],
            ['approach' => 'experience_led', 'subject' => 'Fleet tooling experience', 'alt_subjects' => [], 'claims' => [], 'project_id' => null,
                'body' => 'Over the past years I delivered scheduling tools for courier operators, which cut manual planning time noticeably. Several lessons carry over to fast moving delivery operations. May I send two screenshots?'],
        ]];
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($drafts)]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10]])]);
    }

    public function test_generate_saves_three_grounded_drafts_and_moves_pipeline(): void
    {
        [$user, $o, $contact, $ev] = $this->world();
        $this->fakeAi($ev->id);

        $this->actingAs($user)->postJson(route('agent.outreach.generate', $o), ['channel' => 'email', 'contact_id' => $contact->id])->assertStatus(202);

        $this->assertSame(3, OutreachDraft::where('opportunity_id', $o->id)->count());
        $ps = OutreachDraft::where('approach', 'problem_solving')->first();
        $this->assertSame([$ev->id], $ps->evidence_ids);
        $this->assertSame($contact->id, $ps->contact_id);
        $this->assertSame('drafted', $o->fresh()->status);
        $this->assertStringContainsString('Florence', $ps->fullText());
    }

    public function test_select_then_mark_sent_logs_event_contacts_and_creates_follow_up(): void
    {
        [$user, $o, $contact, $ev] = $this->world();
        $this->fakeAi($ev->id);
        $this->actingAs($user)->postJson(route('agent.outreach.generate', $o), ['channel' => 'email', 'contact_id' => $contact->id]);
        $d = OutreachDraft::where('approach', 'direct')->first();

        $this->postJson(route('agent.outreach.select', $d))->assertOk();
        $this->assertSame('approved', $o->fresh()->status);

        $this->postJson(route('agent.outreach.sent', $d), ['follow_up_days' => 7])->assertOk();
        $this->assertSame('sent', $d->fresh()->status);
        $this->assertSame('contacted', $o->fresh()->status);
        $this->assertSame(1, $o->outreachEvents()->where('event_type', 'sent')->count());
        $this->assertSame(1, $o->followUps()->where('is_auto', true)->count());

        // A sent draft is frozen.
        $this->patchJson(route('agent.outreach.update', $d), ['body' => 'changed'])->assertStatus(409);
    }

    public function test_editing_marks_edited_and_reset_restores_original(): void
    {
        [$user, $o, $contact, $ev] = $this->world();
        $this->fakeAi($ev->id);
        $this->actingAs($user)->postJson(route('agent.outreach.generate', $o), ['channel' => 'email', 'contact_id' => $contact->id]);
        $d = OutreachDraft::where('approach', 'direct')->first();
        $original = $d->body;

        $this->patchJson(route('agent.outreach.update', $d), ['subject' => 'New', 'body' => 'My own wording of this message.'])->assertOk();
        $this->assertSame('edited', $d->fresh()->status);

        $this->postJson(route('agent.outreach.reset', $d))->assertOk();
        $this->assertSame($original, $d->fresh()->body);
    }

    public function test_do_not_contact_and_restrictions_block_drafting(): void
    {
        [$user, $o, $contact] = $this->world();
        Http::fake();

        $contact->update(['restrictions' => 'No unsolicited approaches from agencies']);
        $this->actingAs($user)->postJson(route('agent.outreach.generate', $o), ['channel' => 'email', 'contact_id' => $contact->id])->assertStatus(422);

        $o->company->update(['do_not_contact' => true]);
        $this->postJson(route('agent.outreach.generate', $o), ['channel' => 'email'])->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(0, OutreachDraft::count());
    }

    public function test_other_accounts_cannot_open_the_studio(): void
    {
        [$owner, $o] = $this->world();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('agent.outreach.show', $o))->assertNotFound();
        $this->actingAs($owner)->get(route('agent.outreach.show', $o))->assertOk();
    }
}
