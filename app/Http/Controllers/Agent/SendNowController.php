<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Opportunity;
use App\Models\OutreachDraft;
use App\Models\SharedDocument;
use App\Services\Agent\Leads\LeadMailer;
use App\Services\Agent\Leads\LeadSequenceRunner;
use App\Services\Agent\Mail\GoogleMail;
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

    public function send(Request $request, Opportunity $opportunity, LeadMailer $mailer, GoogleMail $google, LeadSequenceRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'draft_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $status = SendNow::statusFor($user);
        if (! $status['can']) {
            return back()->withInput()->withErrors(['send' => $status['reason']]);
        }

        $company = $opportunity->company;
        $contact = $company->contacts()->where('type', 'email')->find($data['contact_id']);
        if (! $contact || ! filter_var($contact->value, FILTER_VALIDATE_EMAIL)) {
            return back()->withInput()->withErrors(['send' => 'Pick one of this company\'s email contacts.']);
        }
        if ($company->do_not_contact || $contact->status !== 'active') {
            return back()->withInput()->withErrors(['send' => 'Blocked: this company is marked do not contact, or this address bounced / opted out.']);
        }

        $draft = ! empty($data['draft_id']) ? $opportunity->outreachDrafts()->find($data['draft_id']) : null;
        $docs = $request->boolean('attach_docs') ? SharedDocument::where('attach_to_emails', true)->get() : collect();
        $max = (int) config('leads.attach_max_mb', 5) * 1048576;
        $body = rtrim($data['body']);
        if ($docs->isNotEmpty()) {
            $body .= "\n\nDetails you may find useful:\n".$docs->map(fn ($d) => '- '.$d->title.': '.$d->publicUrl())->implode("\n");
        }
        $mail = [
            'subject' => $data['subject'],
            'body' => $body.LeadMailer::optOutFooter($contact->id),
            'to' => $contact->value,
            'attachments' => $docs->filter(fn ($d) => $d->size <= $max && is_file($d->absolutePath()))->values()->all(),
        ];

        try {
            if ($status['mode'] === 'smtp') {
                $mailer->send($mail);
            } else {
                $google->send($user->googleAccount, $user->name ?: $user->email, $mail);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['send' => 'Not sent: '.mb_substr($e->getMessage(), 0, 300)]);
        }

        $now = now();
        $via = $status['mode'] === 'smtp' ? 'app mailbox' : 'Gmail ('.$status['from'].')';
        $seq = $opportunity->sequence;
        $opportunity->outreachEvents()->create([
            'outreach_draft_id' => $draft?->id, 'contact_id' => $contact->id, 'event_type' => 'sent', 'channel' => 'email',
            'notes' => "Sent from the app via {$via}: {$data['subject']}", 'occurred_at' => $now,
        ]);
        if ($draft) {
            $draft->update(['status' => 'sent', 'is_selected' => true, 'sent_at' => $now]);
        }
        if (in_array($opportunity->status, ['discovered', 'researching', 'qualified', 'drafted', 'approved'], true)) {
            $opportunity->update(['status' => 'contacted', 'contacted_at' => $opportunity->contacted_at ?? $now]);
        }
        if ($seq) {
            $runner->noteManualEmail($seq);
        } else {
            $opportunity->followUps()->create([
                'title' => 'Follow up: email to '.$company->name, 'notes' => 'Sent '.$now->format('M j').'. If there is still no reply, send one short follow-up or close it.',
                'due_at' => $now->copy()->addDays(5), 'is_auto' => true,
            ]);
        }
        ActivityLog::record($opportunity, 'email_sent', "Email sent to {$contact->value} via {$via}.");

        return redirect()->route('agent.opportunities.show', $opportunity)->with('status', "Email sent to {$contact->value}.");
    }
}
