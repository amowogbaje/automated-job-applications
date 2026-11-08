<?php

namespace App\Services\Agent\Leads;

use App\Models\ActivityLog;
use App\Models\AgentNotification;
use App\Models\LeadSequence;
use App\Models\Opportunity;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

class LeadSequenceRunner
{
    private const TERMINAL = ['replied', 'won', 'lost', 'rejected', 'archived', 'not_responding'];

    private int $raised = 0;

    public function __construct(private LeadMailer $mailer) {}

    public static function autoOn(): bool
    {
        return (bool) Setting::get('leads.auto_send', false);
    }

    public static function inWindow(CarbonInterface $now): bool
    {
        [$from, $to] = config('leads.send_hours', [8, 17]);

        return $now->hour >= $from && $now->hour < $to;
    }

    /** @return array{processed:int, notifications:int} */
    public function run(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $this->raised = 0;
        $processed = 0;

        $due = LeadSequence::with(['opportunity.company.contacts', 'contact'])
            ->where('state', 'active')->where('next_action_at', '<=', $now)->orderBy('next_action_at')->limit(100)->get();

        foreach ($due as $seq) {
            try {
                $this->process($seq, $now);
                $processed++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $this->emailOwner();
        if ($this->raised) {
            AgentNotification::pushAll(); // one wake-up per run, however many things were raised
        }

        return ['processed' => $processed, 'notifications' => $this->raised];
    }

    private function process(LeadSequence $seq, CarbonInterface $now): void
    {
        $opp = $seq->opportunity;
        $company = $opp->company;

        if (in_array($opp->status, self::TERMINAL, true)) {
            $this->halt($seq, 'Lead is now '.$opp->status);

            return;
        }
        if ($company->do_not_contact) {
            $this->halt($seq, 'Company is marked do not contact');

            return;
        }
        if ($opp->outreachEvents()->whereIn('event_type', ['reply', 'meeting'])->where('created_at', '>=', $seq->created_at)->exists()) {
            $this->halt($seq, 'A reply was logged');

            return;
        }

        $email = $seq->contact && $seq->contact->status === 'active' ? $seq->contact : null;
        $hasPhone = $company->contacts->whereIn('type', ['phone', 'whatsapp'])->where('status', 'active')->isNotEmpty();
        $capLeft = (int) Setting::get('leads.daily_cap', config('leads.daily_cap')) - LeadSequence::where('last_sent_at', '>=', $now->copy()->startOfDay())->count();

        $action = SequencePlanner::decide([
            'mode' => $seq->mode, 'step' => $seq->step, 'total' => $seq->total_steps, 'reminders' => $seq->reminders,
            'next_action_at' => $seq->next_action_at, 'has_phone' => $hasPhone, 'has_email' => (bool) $email,
            'auto_on' => self::autoOn() && $seq->auto_send, 'in_window' => self::inWindow($now), 'cap_left' => $capLeft,
        ], $now);

        match ($action) {
            SequencePlanner::SEND_EMAIL => $this->sendEmail($seq, $opp, $now),
            SequencePlanner::NOTIFY_EMAIL => $this->notifyEmail($seq, $opp, $now),
            SequencePlanner::SWITCH_TO_PHONE => $this->toPhone($seq, $opp, $now),
            SequencePlanner::NOTIFY_PHONE => $this->notifyPhone($seq, $opp, $now),
            SequencePlanner::FINISH => $this->finish($seq, $opp),
            default => null,
        };
    }

    private function sendEmail(LeadSequence $seq, Opportunity $opp, CarbonInterface $now): void
    {
        $n = $seq->step + 1;
        $mail = $this->mailer->compose($seq, $n);
        try {
            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            $seq->failures++;
            if ($seq->failures >= 2) {
                $seq->state = 'paused';
                $seq->stop_reason = 'Email could not be sent';
                $this->notify('failure', 'Follow-up email failed for '.$opp->company->name, 'Sending failed twice and the sequence is paused. Check your MAIL_* settings. '.mb_substr($e->getMessage(), 0, 160), $opp, "fail:{$opp->id}:".$now->timestamp);
            } else {
                $seq->next_action_at = $now->copy()->addHour();
            }
            $seq->save();

            return;
        }
        $this->recordSent($seq, $opp, 'email', "Automatic follow-up {$n} to {$mail['to']}: {$mail['subject']}", $now);
        $seq->failures = 0;
        $this->advanceEmail($seq, $now);
    }

    private function notifyEmail(LeadSequence $seq, Opportunity $opp, CarbonInterface $now): void
    {
        $n = $seq->step + 1;
        $mail = $this->mailer->compose($seq, $n);
        $this->notify('email_due', "Follow-up email {$n} is due for {$opp->company->name}",
            "Automatic sending is off, so nothing was sent. To: {$mail['to']}\nSubject: {$mail['subject']}\n\n{$mail['body']}", $opp, "email:{$opp->id}:{$n}");
        $seq->next_action_at = $now->copy()->addDay();
        $seq->save();
    }

    private function toPhone(LeadSequence $seq, Opportunity $opp, CarbonInterface $now): void
    {
        $why = $seq->step > 0 ? "No reply to {$seq->step} email".($seq->step > 1 ? 's' : '').' yet.' : 'No usable email.';
        $seq->fill(['mode' => 'manual', 'step' => 0, 'total_steps' => 1, 'reminders' => 0, 'next_action_at' => $now])->save();
        $this->notifyPhone($seq, $opp, $now, $why);
    }

    private function notifyPhone(LeadSequence $seq, Opportunity $opp, CarbonInterface $now, ?string $why = null): void
    {
        $phone = $opp->company->contacts->whereIn('type', ['phone', 'whatsapp'])->where('status', 'active')->first();
        $who = $opp->company->name;
        $text = 'Hi, this is '.LeadMailer::ownerName().". I'd love to help {$who}".($opp->primaryService ? ' with '.$opp->primaryService->name : '').'. Do you have a moment to chat?';
        $this->notify('phone_due', ($why ? $why.' ' : '')."Message {$who} on WhatsApp or call",
            "Phone: {$phone?->value}\nSuggested message: {$text}\n\nOpen the lead and tap \"I messaged them\" or \"They replied\". If you do neither, it will be marked Not responding after the reminders.",
            $opp, "phone:{$opp->id}:{$seq->reminders}");
        $seq->reminders++;
        $seq->next_action_at = $now->copy()->addDays((int) config('leads.manual_wait_days', 3));
        $seq->save();
    }

    private function finish(LeadSequence $seq, Opportunity $opp): void
    {
        $reachable = $opp->company->contacts->where('status', 'active')->whereIn('type', ['email', 'phone', 'whatsapp'])->isNotEmpty();
        $status = $reachable ? 'not_responding' : 'lost';
        $reason = $reachable ? 'No response after the follow-up sequence' : 'No reachable contact left (bounced or opted out)';
        $opp->update(['status' => $status, 'rejection_reason' => null]);
        $opp->outreachEvents()->create(['event_type' => 'no_response', 'channel' => null, 'notes' => $reason, 'occurred_at' => now()]);
        ActivityLog::record($opp, 'lead_closed', $reason);
        $seq->fill(['state' => 'done', 'stop_reason' => $reason])->save();
        $this->notify('info', $opp->company->name.' marked '.config('opportunity.statuses')[$status], $reason.'. You can reopen it any time by changing its status.', $opp, "closed:{$opp->id}:".now()->timestamp);
    }

    private function halt(LeadSequence $seq, string $reason): void
    {
        $seq->fill(['state' => 'stopped', 'stop_reason' => $reason])->save();
    }

    private function advanceEmail(LeadSequence $seq, CarbonInterface $now): void
    {
        $seq->step++;
        $seq->last_sent_at = $now;
        $seq->next_action_at = $now->copy()->addDays(SequencePlanner::emailDelayAfter($seq->step, config('leads.email_delays'), (int) config('leads.grace_days', 4)));
        $seq->save();
    }

    private function recordSent(LeadSequence $seq, Opportunity $opp, string $channel, string $notes, CarbonInterface $now): void
    {
        $opp->outreachEvents()->create(['event_type' => 'sent', 'channel' => $channel, 'notes' => $notes, 'occurred_at' => $now]);
        if (in_array($opp->status, ['discovered', 'researching', 'qualified', 'drafted', 'approved'], true)) {
            $opp->update(['status' => 'contacted', 'contacted_at' => $opp->contacted_at ?? $now]);
        }
    }

    // ---- actions the owner takes from the UI ----

    /** "I sent it" (email step done by hand, or phone/WhatsApp message sent). */
    public function markSent(LeadSequence $seq, string $channel = 'whatsapp'): void
    {
        $opp = $seq->opportunity->loadMissing('company');
        $now = now();
        if ($seq->mode === 'email') {
            $this->recordSent($seq, $opp, 'email', 'Follow-up email sent manually', $now);
            $this->advanceEmail($seq, $now);

            return;
        }
        $this->recordSent($seq, $opp, in_array($channel, ['whatsapp', 'phone'], true) ? $channel : 'whatsapp', 'Phone/WhatsApp follow-up sent', $now);
        $seq->fill(['step' => 1, 'last_sent_at' => $now, 'state' => 'active',
            'next_action_at' => $now->copy()->addDays((int) config('leads.after_manual_days', 5))])->save();
    }

    public function markReplied(LeadSequence $seq): void
    {
        $opp = $seq->opportunity;
        $opp->outreachEvents()->create(['event_type' => 'reply', 'channel' => null, 'notes' => 'Marked as replied from the lead page', 'occurred_at' => now()]);
        $opp->update(['status' => 'replied']);
        $seq->fill(['state' => 'done', 'stop_reason' => 'They replied'])->save();
        ActivityLog::record($opp, 'lead_replied', 'Marked as replied.');
        AgentNotification::where('opportunity_id', $opp->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function notify(string $kind, string $title, ?string $body, Opportunity $opp, string $key): void
    {
        if (AgentNotification::raise($kind, $title, $body, $opp->id, $key)) {
            $this->raised++;
        }
    }

    private function emailOwner(): void
    {
        $to = LeadMailer::ownerEmail();
        if (! $this->raised || ! $to || ! Setting::get('leads.email_owner', true)) {
            return;
        }
        try {
            $items = AgentNotification::whereNull('read_at')->latest()->limit($this->raised)->pluck('title')->implode("\n- ");
            Mail::raw("You have {$this->raised} new lead notification(s):\n\n- {$items}\n\nOpen ".url('/agent/notifications'), fn ($m) => $m->to($to)->subject("{$this->raised} lead follow-up(s) need you"));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
