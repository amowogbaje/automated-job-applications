<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\GoogleAccount;
use App\Models\Opportunity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendNowTest extends TestCase
{
    use RefreshDatabase;

    private function lead(): Opportunity
    {
        $c = Company::create(['name' => 'Acme']);
        Contact::create(['company_id' => $c->id, 'type' => 'email', 'value' => 'ada@acme.test', 'label' => 'x', 'source_url' => 'uploaded: t', 'status' => 'active', 'created_by' => 'manual']);

        return Opportunity::create(['company_id' => $c->id, 'title' => 'Lead: Acme', 'type' => 'lead', 'source' => 'uploaded', 'source_hash' => 'h1', 'status' => 'qualified']);
    }

    private function payload(Opportunity $o): array
    {
        return ['contact_id' => $o->company->contacts()->first()->id, 'subject' => 'Hello', 'body' => 'Hi Ada'];
    }

    public function testMailboxOwnerSendsThroughAppMail(): void
    {
        Mail::fake();
        config(['leads.smtp_user_email' => 'amowogbajegideon@gmail.com']);
        $user = User::factory()->create(['email' => 'amowogbajegideon@gmail.com']);
        config(['agent.owner_emails' => [$user->email]]);
        $o = $this->lead();

        $this->actingAs($user)->post(route('agent.send.store', $o), $this->payload($o))->assertRedirect(route('agent.opportunities.show', $o));
        $this->assertSame('contacted', $o->fresh()->status);
        $this->assertSame('sent', $o->outreachEvents()->first()->event_type);
    }

    public function testOtherUserIsBlockedUntilGoogleConnected(): void
    {
        Mail::fake();
        Http::fake();
        $user = User::factory()->create(['email' => 'amowogbajeflorence@gmail.com']);
        config(['agent.owner_emails' => [$user->email]]);
        $o = $this->lead();

        $this->actingAs($user)->post(route('agent.send.store', $o), $this->payload($o))->assertSessionHasErrors('send');
        $this->assertSame('qualified', $o->fresh()->status);
        Http::assertNothingSent();
    }

    public function testOtherUserSendsThroughGmailApi(): void
    {
        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'], 200)]);
        $user = User::factory()->create(['email' => 'amowogbajeflorence@gmail.com']);
        config(['agent.owner_emails' => [$user->email]]);
        GoogleAccount::create(['user_id' => $user->id, 'email' => $user->email, 'refresh_token' => 'r', 'access_token' => 'a', 'expires_at' => now()->addHour()]);
        $o = $this->lead();

        $this->actingAs($user)->post(route('agent.send.store', $o), $this->payload($o))->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages/send') && ! empty($r['raw']));
        $this->assertSame('contacted', $o->fresh()->status);
    }

    public function testSwitchOffBlocksEvenTheMailboxOwner(): void
    {
        Mail::fake();
        Setting::put('leads.send_now_enabled', false);
        $user = User::factory()->create(['email' => 'amowogbajegideon@gmail.com']);
        config(['agent.owner_emails' => [$user->email], 'leads.smtp_user_email' => $user->email]);
        $o = $this->lead();

        $this->actingAs($user)->post(route('agent.send.store', $o), $this->payload($o))->assertSessionHasErrors('send');
    }

    public function testDoNotContactIsRespected(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'amowogbajegideon@gmail.com']);
        config(['agent.owner_emails' => [$user->email], 'leads.smtp_user_email' => $user->email]);
        $o = $this->lead();
        $o->company->update(['do_not_contact' => true]);

        $this->actingAs($user)->post(route('agent.send.store', $o), $this->payload($o))->assertSessionHasErrors('send');
    }
}
