<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OutreachDraft;
use App\Models\SharedDocument;
use App\Services\Agent\Leads\LeadMailer;
use App\Models\ScheduledEmail;
use App\Services\Agent\Leads\LocalTime;
use App\Services\Agent\Mail\OutboundEmail;
use App\Services\Agent\Mail\SendNow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SendNowController extends Controller
{
    public function compose(Request $request, Opportunity $opportunity, LeadMailer $mailer)
    {
        $opportunity->load('company.contacts');
        $contacts = $opportunity->company->contacts->where('type', 'email')->where('status', 'active')->values();
        $draft = $request->filled('draft') ? $opportunity->outreachDrafts()->find($request->input('draft')) : null;

        if ($draft) {
            $subject = (string) $draft->subject;
            $body = $draft->fullText();
            $contact = $draft->contact && $draft->contact->type === 'email' ? $draft->contact : $contacts->first();
        } else {
            $contact = $contacts->first();
            $m = $mailer->render($opportunity, $contact, $opportunity->sequence ? min($opportunity->sequence->step + 1, 3) : 1);
            $subject = $m['subject'];
            $body = $m['body'];
        }

        return view('agent.send.compose', [
            'opportunity' => $opportunity, 'company' => $opportunity->company, 'contacts' => $contacts, 'selected' => $contact,
            'draft' => $draft, 'subject' => $subject, 'body' => $body,
            'status' => SendNow::statusFor($request->user()),
            'docs' => SharedDocument::where('attach_to_emails', true)->get(),
        ]);
    }

    public function send(Request $request, Opportunity $opportunity, OutboundEmail $out): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'draft_id' => ['nullable', 'integer'],
            'send_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'tz_offset' => ['nullable', 'integer', 'between:-840,840'],
        ]);
        $user = $request->user();
        $contact = $opportunity->company->contacts()->where('type', 'email')->find($data['contact_id']);
        $draft = ! empty($data['draft_id']) ? $opportunity->outreachDrafts()->find($data['draft_id']) : null;

        try {
            $out->guard($user, $opportunity, $contact); // fail early, also when scheduling
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['send' => $e->getMessage()]);
        }

        // Send later
        if ($request->input('action') === 'schedule') {
            if (empty($data['send_at'])) {
                return back()->withInput()->withErrors(['send' => 'Pick a date and time to send.']);
            }
            $at = LocalTime::toApp($data['send_at'], (int) ($data['tz_offset'] ?? 0));
            if ($at->lt(now()->addMinute()) || $at->gt(now()->addDays(90))) {
                return back()->withInput()->withErrors(['send' => 'Pick a time between 1 minute and 90 days from now.']);
            }
            ScheduledEmail::create([
                'user_id' => $user->id, 'opportunity_id' => $opportunity->id, 'contact_id' => $contact->id, 'outreach_draft_id' => $draft?->id,
                'subject' => $data['subject'], 'body' => $data['body'], 'attach_docs' => $request->boolean('attach_docs'), 'send_at' => $at,
            ]);

            return redirect()->route('agent.notifications.index')->with('status', "Scheduled for {$data['send_at']} (your time). You can cancel it under Alerts.");
        }

        try {
            $to = $out->deliver($user, $opportunity, $contact, $data['subject'], $data['body'], $request->boolean('attach_docs'), $draft);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['send' => 'Not sent: '.$e->getMessage()]);
        }

        return redirect()->route('agent.opportunities.show', $opportunity)->with('status', "Email sent to {$to}.");
    }
}
