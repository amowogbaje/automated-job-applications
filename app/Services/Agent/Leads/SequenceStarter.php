<?php

namespace App\Services\Agent\Leads;

use App\Models\Contact;
use App\Models\LeadSequence;
use App\Models\Opportunity;

class SequenceStarter
{
    /** Starts (or restarts) the follow-up sequence: email if there is a usable email, else phone reminders. */
    public function start(Opportunity $opp, ?Contact $email = null, bool $autoSend = true): ?LeadSequence
    {
        $email ??= $opp->company->contacts()->where('type', 'email')->where('status', 'active')->first();
        $hasPhone = $opp->company->contacts()->whereIn('type', ['phone', 'whatsapp'])->where('status', 'active')->exists();
        if (! $email && ! $hasPhone) {
            return null;
        }
        $emailMode = (bool) $email;

        return LeadSequence::updateOrCreate(['opportunity_id' => $opp->id], [
            'contact_id' => $email?->id,
            'mode' => $emailMode ? 'email' : 'manual',
            'state' => 'active',
            'step' => 0,
            'total_steps' => $emailMode ? count(config('leads.email_delays')) : 1,
            'reminders' => 0,
            'failures' => 0,
            'auto_send' => $autoSend,
            'next_action_at' => now(),
            'stop_reason' => null,
        ]);
    }
}
