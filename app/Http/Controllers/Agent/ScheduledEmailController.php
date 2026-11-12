<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ScheduledEmail;
use App\Services\Agent\Mail\OutboundEmail;
use Illuminate\Http\RedirectResponse;

class ScheduledEmailController extends Controller
{
    public function cancel(ScheduledEmail $email): RedirectResponse
    {
        if ($email->status === 'pending') {
            $email->update(['status' => 'cancelled']);
        }

        return back()->with('status', 'Scheduled email cancelled.');
    }

    /** Send a scheduled email right now instead of waiting. */
    public function sendNow(ScheduledEmail $email, OutboundEmail $out): RedirectResponse
    {
        if (ScheduledEmail::where('id', $email->id)->where('status', 'pending')->update(['status' => 'sending']) !== 1) {
            return back()->withErrors(['send' => 'This one is no longer pending.']);
        }
        try {
            $to = $out->deliver($email->user, $email->opportunity, $email->contact, $email->subject, $email->body, $email->attach_docs, $email->outreach_draft_id ? \App\Models\OutreachDraft::find($email->outreach_draft_id) : null);
            $email->update(['status' => 'sent', 'sent_at' => now()]);

            return back()->with('status', "Sent to {$to}.");
        } catch (\Throwable $e) {
            $email->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return back()->withErrors(['send' => 'Not sent: '.$e->getMessage()]);
        }
    }
}
