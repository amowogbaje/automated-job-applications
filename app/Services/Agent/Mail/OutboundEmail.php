<?php

namespace App\Services\Agent\Mail;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\OutreachDraft;
use App\Models\SharedDocument;
use App\Models\User;
use App\Services\Agent\Leads\LeadMailer;
use App\Services\Agent\Leads\LeadSequenceRunner;

/** One place that actually sends a hand-written email (Send now and Scheduled send both use it). */
class OutboundEmail
{
    public function __construct(private LeadMailer $mailer, private GoogleMail $google, private LeadSequenceRunner $runner) {}

    /** Checks that apply to every send, at the moment of sending. @throws \RuntimeException */
    public function guard(User $user, Opportunity $opp, ?Contact $contact): void
    {
        $status = SendNow::statusFor($user);
        if (! $status['can']) {
            throw new \RuntimeException($status['reason']);
        }
        if (! $contact || $contact->type !== 'email' || ! filter_var($contact->value, FILTER_VALIDATE_EMAIL) || $contact->company_id !== $opp->company_id) {
            throw new \RuntimeException("Pick one of this company's email contacts.");
        }
        if ($opp->company->do_not_contact || $contact->status !== 'active') {
            throw new \RuntimeException('Blocked: this company is marked do not contact, or this address bounced / opted out.');
        }
    }

    /** @throws \RuntimeException with a message that is safe to show */
    public function deliver(User $user, Opportunity $opp, Contact $contact, string $subject, string $body, bool $attachDocs = false, ?OutreachDraft $draft = null): string
    {
        $this->guard($user, $opp, $contact);
        $status = SendNow::statusFor($user);

        $docs = $attachDocs ? SharedDocument::where('attach_to_emails', true)->get() : collect();
        $max = (int) config('leads.attach_max_mb', 5) * 1048576;
        $body = rtrim($body);
        if ($docs->isNotEmpty()) {
            $body .= "\n\nDetails you may find useful:\n".$docs->map(fn ($d) => '- '.$d->title.': '.$d->publicUrl())->implode("\n");
        }
        $mail = [
            'subject' => $subject,
            'body' => $body.LeadMailer::optOutFooter($contact->id),
            'to' => $contact->value,
            'attachments' => $docs->filter(fn ($d) => $d->size <= $max && is_file($d->absolutePath()))->values()->all(),
        ];

        try {
            if ($status['mode'] === 'smtp') {
                $this->mailer->send($mail);
            } else {
                $this->google->send($user->googleAccount, $user->name ?: $user->email, $mail);
            }
        } catch (\Throwable $e) {
            report($e);
            throw new \RuntimeException(mb_substr($e->getMessage(), 0, 300), 0, $e);
        }

        $this->record($opp, $contact, $subject, $status, $draft);

        return $contact->value;
    }

    private function record(Opportunity $opp, Contact $contact, string $subject, array $status, ?OutreachDraft $draft): void
    {
        $now = now();
        $via = $status['mode'] === 'smtp' ? 'app mailbox' : 'Gmail ('.$status['from'].')';
        $opp->outreachEvents()->create([
            'outreach_draft_id' => $draft?->id, 'contact_id' => $contact->id, 'event_type' => 'sent', 'channel' => 'email',
            'notes' => "Sent from the app via {$via}: {$subject}", 'occurred_at' => $now,
        ]);
        $draft?->update(['status' => 'sent', 'is_selected' => true, 'sent_at' => $now]);
        if (in_array($opp->status, ['discovered', 'researching', 'qualified', 'drafted', 'approved'], true)) {
            $opp->update(['status' => 'contacted', 'contacted_at' => $opp->contacted_at ?? $now]);
        }
        if ($opp->sequence) {
            $this->runner->noteManualEmail($opp->sequence);
        } else {
            $opp->followUps()->create([
                'title' => 'Follow up: email to '.$opp->company->name, 'notes' => 'Sent '.$now->format('M j').'. If there is still no reply, send one short follow-up or close it.',
                'due_at' => $now->copy()->addDays(5), 'is_auto' => true,
            ]);
        }
        ActivityLog::record($opp, 'email_sent', "Email sent to {$contact->value} via {$via}.");
    }
}
