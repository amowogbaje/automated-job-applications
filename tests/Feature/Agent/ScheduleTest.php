<?php

namespace Tests\Feature;

use App\Models\AgentNotification;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\ScheduledEmail;
use App\Models\User;
use App\Models\WhatsappReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email' => 'amowogbajegideon@gmail.com']);
        config(['agent.owner_emails' => [$this->user->email], 'leads.smtp_user_email' => $this->user->email]);
    }

    private function lead(): Opportunity
    {
        $c = Company::create(['name' => 'Acme']);
        foreach ([['email', 'ada@acme.test'], ['phone', '+2348031234567']] as [$t, $v]) {
            Contact::create(['company_id' => $c->id, 'type' => $t, 'value' => $v, 'label' => 'x', 'source_url' => 'uploaded: t', 'status' => 'active', 'created_by' => 'manual']);
        }

        return Opportunity::create(['company_id' => $c->id, 'title' => 'Lead: Acme', 'type' => 'lead', 'source' => 'uploaded', 'source_hash' => 'h', 'status' => 'qualified']);
    }

    private function scheduled(Opportunity $o, string $when = '-1 minute'): ScheduledEmail
    {
        return ScheduledEmail::create([
            'user_id' => $this->user->id, 'opportunity_id' => $o->id, 'contact_id' => $o->company->contacts()->where('type', 'email')->value('id'),
            'subject' => 'Hello', 'body' => 'Hi Ada', 'send_at' => now()->modify($when),
        ]);
    }

    public function testScheduleFormStoresEmailForLater(): void
    {
        $o = $this->lead();
        $at = now()->addDay()->format('Y-m-d\TH:i');
        $this->actingAs($this->user)->post(route('agent.send.store', $o), [
            'action' => 'schedule', 'contact_id' => $o->company->contacts()->where('type', 'email')->value('id'),
            'subject' => 'Hello', 'body' => 'Hi', 'send_at' => $at, 'tz_offset' => 0,
        ])->assertRedirect(route('agent.notifications.index'));
        $this->assertSame(1, ScheduledEmail::where('status', 'pending')->count());
        $this->assertSame('qualified', $o->fresh()->status); // nothing sent yet
    }

    public function testScheduleRejectsPastTimes(): void
    {
        $o = $this->lead();
        $this->actingAs($this->user)->post(route('agent.send.store', $o), [
            'action' => 'schedule', 'contact_id' => $o->company->contacts()->where('type', 'email')->value('id'),
            'subject' => 'Hello', 'body' => 'Hi', 'send_at' => now()->subHour()->format('Y-m-d\TH:i'), 'tz_offset' => 0,
        ])->assertSessionHasErrors('send');
        $this->assertSame(0, ScheduledEmail::count());
    }

    public function testCommandSendsDueEmailOnce(): void
    {
        Mail::fake();
        $o = $this->lead();
        $e = $this->scheduled($o);

        $this->artisan('emails:send-scheduled')->assertSuccessful();
        $this->artisan('emails:send-scheduled')->assertSuccessful();

        $this->assertSame('sent', $e->fresh()->status);
        $this->assertSame(1, $o->outreachEvents()->where('event_type', 'sent')->count());
        $this->assertSame('contacted', $o->fresh()->status);
    }

    public function testCommandLeavesFutureEmailsAlone(): void
    {
        Mail::fake();
        $e = $this->scheduled($this->lead(), '+2 hours');
        $this->artisan('emails:send-scheduled')->assertSuccessful();
        $this->assertSame('pending', $e->fresh()->status);
    }

    public function testCommandSkipsLeadThatAlreadyReplied(): void
    {
        Mail::fake();
        $o = $this->lead();
        $o->update(['status' => 'replied']);
        $e = $this->scheduled($o);

        $this->artisan('emails:send-scheduled')->assertSuccessful();
        $this->assertSame('skipped', $e->fresh()->status);
        Mail::assertNothingSent();
    }

    public function testWhatsappReminderBecomesNotificationWithLink(): void
    {
        $o = $this->lead();
        $r = WhatsappReminder::create(['opportunity_id' => $o->id, 'phone' => '+2348031234567', 'message' => 'Hi Ada', 'remind_at' => now()->subMinute()]);

        $this->artisan('alerts:dispatch')->assertSuccessful();

        $this->assertSame('due', $r->fresh()->status);
        $n = AgentNotification::where('kind', 'whatsapp_due')->first();
        $this->assertSame(route('agent.whatsapp.show', $r), $n->url);

        $this->artisan('alerts:dispatch')->assertSuccessful(); // not duplicated
        $this->assertSame(1, AgentNotification::where('kind', 'whatsapp_due')->count());
    }

    public function testDueEndpointAndWhatsappLink(): void
    {
        $o = $this->lead();
        $r = WhatsappReminder::create(['opportunity_id' => $o->id, 'phone' => '+2348031234567', 'message' => 'Hi Ada & co', 'remind_at' => now(), 'status' => 'due']);

        $res = $this->actingAs($this->user)->getJson(route('agent.whatsapp.due'))->assertOk();
        $res->assertJsonPath('count', 1);
        $this->assertSame('https://wa.me/2348031234567?text=Hi%20Ada%20%26%20co', $res->json('items.0.wa'));
    }

    public function testMarkingSentLogsEventAndClosesReminder(): void
    {
        $o = $this->lead();
        $r = WhatsappReminder::create(['opportunity_id' => $o->id, 'phone' => '+2348031234567', 'message' => 'Hi', 'remind_at' => now(), 'status' => 'due']);

        $this->actingAs($this->user)->post(route('agent.whatsapp.sent', $r))->assertRedirect();
        $this->assertSame('sent', $r->fresh()->status);
        $this->assertSame('whatsapp', $o->outreachEvents()->first()->channel);
    }

    public function testScheduleWhatsappFromLeadPage(): void
    {
        $o = $this->lead();
        $this->actingAs($this->user)->post(route('agent.whatsapp.store', $o), [
            'message' => 'Hi Ada', 'remind_at' => now()->addHours(3)->format('Y-m-d\TH:i'), 'tz_offset' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending', WhatsappReminder::first()->status);
        $this->assertSame('+2348031234567', WhatsappReminder::first()->phone);
    }
}
