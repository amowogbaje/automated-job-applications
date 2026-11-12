<?php

namespace App\Console\Commands;

use App\Models\AgentNotification;
use App\Models\OutreachDraft;
use App\Models\ScheduledEmail;
use App\Services\Agent\Mail\OutboundEmail;
use Illuminate\Console\Command;

class SendScheduledEmails extends Command
{
    protected $signature = 'emails:send-scheduled';

    protected $description = 'Send emails you scheduled, when their time comes';

    private const TERMINAL = ['replied', 'won', 'lost', 'rejected', 'archived', 'not_responding'];

    public function handle(OutboundEmail $out): int
    {
        $sent = 0;
        $due = ScheduledEmail::where('status', 'pending')->where('send_at', '<=', now())->orderBy('send_at')->limit(25)->get();

        foreach ($due as $e) {
            // claim it so two overlapping runs can never both send it
            if (ScheduledEmail::where('id', $e->id)->where('status', 'pending')->update(['status' => 'sending']) !== 1) {
                continue;
            }
            $e->load('opportunity.company', 'contact', 'user');
            $name = $e->opportunity->company->name;

            if (in_array($e->opportunity->status, self::TERMINAL, true)) {
                $e->update(['status' => 'skipped', 'error' => 'Lead is now '.$e->opportunity->status]);
                AgentNotification::raise('info', "Scheduled email to {$name} was skipped", "The lead is now {$e->opportunity->status}, so it was not sent.", $e->opportunity_id, "sched-skip:{$e->id}");
                continue;
            }

            try {
                $to = $out->deliver($e->user, $e->opportunity, $e->contact, $e->subject, $e->body, $e->attach_docs, $e->outreach_draft_id ? OutreachDraft::find($e->outreach_draft_id) : null);
                $e->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
                AgentNotification::raise('info', "Scheduled email sent to {$name}", "Sent to {$to}: {$e->subject}", $e->opportunity_id, "sched-sent:{$e->id}");
                $sent++;
            } catch (\Throwable $ex) {
                $e->attempts++;
                $retry = $e->attempts < 3 && ! str_starts_with($ex->getMessage(), 'Blocked') && ! str_contains($ex->getMessage(), 'switched off');
                $e->fill(['status' => $retry ? 'pending' : 'failed', 'error' => mb_substr($ex->getMessage(), 0, 300), 'send_at' => $retry ? now()->addMinutes(5) : $e->send_at])->save();
                if (! $retry) {
                    AgentNotification::raise('failure', "Scheduled email to {$name} failed", mb_substr($ex->getMessage(), 0, 300), $e->opportunity_id, "sched-fail:{$e->id}");
                }
            }
        }
        $this->info("Scheduled emails sent: {$sent}");
        if ($sent || AgentNotification::whereNull('read_at')->where('created_at', '>=', now()->subMinute())->exists()) {
            AgentNotification::pushAll();
        }

        return self::SUCCESS;
    }
}
