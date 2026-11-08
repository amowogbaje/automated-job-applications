<?php

namespace Tests\Feature;

use App\Models\AgentNotification;
use App\Models\Company;
use App\Models\LeadSequence;
use App\Models\Opportunity;
use App\Models\Setting;
use App\Models\User;
use App\Services\Agent\Leads\LeadImporter;
use App\Services\Agent\Leads\LeadSequenceRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    private function rows(): array
    {
        return [
            ['company' => 'Acme Salon', 'contact_name' => 'Ada Obi', 'email' => 'ada@acme.test', 'phone' => '08031234567'],
            ['company' => 'Phone Only Motors', 'phone' => '08020001111'],
            ['company' => 'No Contact Ltd'],
        ];
    }

    private function import(): array
    {
        return app(LeadImporter::class)->import($this->rows(), ['batch' => 't', 'start_sequence' => true, 'auto_send' => true]);
    }

    public function testImportCreatesLeadsAndSequences(): void
    {
        $r = $this->import();
        $this->assertSame(3, $r['created']);
        $this->assertSame(1, $r['no_contact']);
        $this->assertSame('email', Opportunity::whereHas('company', fn ($c) => $c->where('name', 'Acme Salon'))->first()->sequence->mode);
        $this->assertSame('manual', Opportunity::whereHas('company', fn ($c) => $c->where('name', 'Phone Only Motors'))->first()->sequence->mode);
        $this->assertSame(2, LeadSequence::count());
        $this->assertSame('+2348020001111', Company::where('name', 'Phone Only Motors')->first()->contacts()->first()->value);

        $again = $this->import();
        $this->assertSame(0, $again['created']); // already in pipeline
        $this->assertSame(3, $again['existing']);
    }

    public function testAutoEmailGoesOutOnlyWhenSwitchedOn(): void
    {
        Mail::fake();
        $this->import();
        $this->travelTo(now()->setTime(10, 0));

        app(LeadSequenceRunner::class)->run();
        Mail::assertNothingSent(); // global switch off: only a notification
        $this->assertTrue(AgentNotification::where('kind', 'email_due')->exists());

        Setting::put('leads.auto_send', true);
        app(LeadSequenceRunner::class)->run();
        $seq = Opportunity::whereHas('company', fn ($c) => $c->where('name', 'Acme Salon'))->first()->sequence;
        $this->assertSame(1, $seq->step);
        $this->assertSame('contacted', $seq->opportunity->fresh()->status);
    }

    public function testPhoneOnlyLeadIsNotifiedThenMarkedNotResponding(): void
    {
        $this->import();
        $opp = Opportunity::whereHas('company', fn ($c) => $c->where('name', 'Phone Only Motors'))->first();

        $runner = app(LeadSequenceRunner::class);
        $runner->run();
        $this->assertTrue(AgentNotification::where('kind', 'phone_due')->where('opportunity_id', $opp->id)->exists());

        foreach ([4, 8, 12] as $days) { // reminders, then give up
            $this->travelTo(now()->addDays(4));
            $runner->run();
        }
        $this->assertSame('not_responding', $opp->fresh()->status);
        $this->assertSame('done', $opp->sequence->fresh()->state);
    }

    public function testReplyStopsSequence(): void
    {
        $this->import();
        $opp = Opportunity::whereHas('company', fn ($c) => $c->where('name', 'Acme Salon'))->first();
        app(LeadSequenceRunner::class)->markReplied($opp->sequence);
        $this->assertSame('replied', $opp->fresh()->status);
        $this->assertSame('done', $opp->sequence->fresh()->state);
    }

    public function testOptOutLinkStopsEmails(): void
    {
        $this->import();
        $contact = Company::where('name', 'Acme Salon')->first()->contacts()->where('type', 'email')->first();
        $url = \Illuminate\Support\Facades\URL::signedRoute('leads.unsubscribe', ['contact' => $contact->id]);
        $this->get($url)->assertOk();
        $this->assertSame('opted_out', $contact->fresh()->status);
        $this->get('/leads/unsubscribe/'.$contact->id)->assertForbidden(); // unsigned
    }

    public function testConvertAndRecordTransaction(): void
    {
        $user = User::factory()->create();
        $this->import();
        $opp = Opportunity::whereHas('company', fn ($c) => $c->where('name', 'Acme Salon'))->first();

        $this->actingAs($user)->post(route('agent.leads.convert', $opp))->assertRedirect();
        $this->assertTrue($opp->company->fresh()->is_customer);
        $this->assertSame('won', $opp->fresh()->status);

        $this->post(route('agent.transactions.store', $opp->company), [
            'description' => 'Website', 'amount' => 150000, 'currency' => 'ngn', 'status' => 'paid', 'occurred_on' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertSame('NGN', $opp->company->transactions()->first()->currency);
        $this->get(route('agent.customers.show', $opp->company))->assertOk()->assertSee('150,000.00');
    }
}
