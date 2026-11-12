<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentNotification;
use App\Models\Opportunity;
use App\Models\WhatsappReminder;
use App\Services\Agent\Leads\LeadSequenceRunner;
use App\Services\Agent\Leads\LocalTime;
use App\Services\Agent\Leads\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    /** Schedule a reminder from a lead page. */
    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:1500'],
            'remind_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'tz_offset' => ['nullable', 'integer', 'between:-840,840'],
        ]);
        $phone = $opportunity->company->contacts()->whereIn('type', ['phone', 'whatsapp'])->where('status', 'active')
            ->when(! empty($data['contact_id']), fn ($q) => $q->where('id', $data['contact_id']))->first();
        if (! $phone || ! Phone::normalize($phone->value, config('leads.default_country_code', '234'))) {
            return back()->withInput()->withErrors(['whatsapp' => 'This lead has no usable phone number.']);
        }
        $at = LocalTime::toApp($data['remind_at'], (int) ($data['tz_offset'] ?? 0));
        if ($at->gt(now()->addDays(180))) {
            return back()->withInput()->withErrors(['whatsapp' => 'Pick a time within the next 6 months.']);
        }

        WhatsappReminder::create([
            'opportunity_id' => $opportunity->id, 'contact_id' => $phone->id,
            'phone' => Phone::normalize($phone->value, config('leads.default_country_code', '234')),
            'message' => $data['message'], 'remind_at' => $at->lt(now()) ? now() : $at,
        ]);

        return back()->with('status', 'Reminder set. You will get a notification with the WhatsApp message ready to send.');
    }

    /** The page a notification opens: message ready, one big button. */
    public function show(WhatsappReminder $reminder)
    {
        AgentNotification::where('url', route('agent.whatsapp.show', $reminder))->whereNull('read_at')->update(['read_at' => now()]);

        return view('agent.whatsapp.show', ['reminder' => $reminder->load('opportunity.company')]);
    }

    public function update(Request $request, WhatsappReminder $reminder): JsonResponse
    {
        $reminder->update($request->validate(['message' => ['required', 'string', 'max:1500']]));

        return response()->json(['url' => $reminder->waUrl()]);
    }

    public function sent(WhatsappReminder $reminder, LeadSequenceRunner $runner): RedirectResponse
    {
        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
        $opp = $reminder->opportunity;
        if ($opp->sequence && $opp->sequence->mode === 'manual' && $opp->sequence->state === 'active') {
            $runner->markSent($opp->sequence, 'whatsapp'); // logs the event and schedules the wait
        } else {
            $opp->outreachEvents()->create(['event_type' => 'sent', 'channel' => 'whatsapp', 'notes' => 'WhatsApp message sent', 'occurred_at' => now()]);
            if (in_array($opp->status, ['discovered', 'researching', 'qualified', 'drafted', 'approved'], true)) {
                $opp->update(['status' => 'contacted', 'contacted_at' => $opp->contacted_at ?? now()]);
            }
        }

        return redirect()->route('agent.opportunities.show', $opp)->with('status', 'Logged as sent.');
    }

    public function snooze(Request $request, WhatsappReminder $reminder): RedirectResponse
    {
        $mins = (int) $request->input('minutes', 60);
        $reminder->update(['status' => 'pending', 'remind_at' => now()->addMinutes(max(5, min($mins, 1440))), 'nags' => 0]);

        return redirect()->route('agent.notifications.index')->with('status', 'Snoozed. I will remind you again.');
    }

    public function dismiss(WhatsappReminder $reminder): RedirectResponse
    {
        $reminder->update(['status' => 'dismissed']);

        return redirect()->route('agent.notifications.index')->with('status', 'Reminder dismissed.');
    }

    /** Polled by open pages so they can pop a banner and play the "time to send" sound. */
    public function due(): JsonResponse
    {
        try {
            $rows = WhatsappReminder::with('opportunity.company')->where('status', 'due')->orderBy('remind_at')->limit(10)->get();
        } catch (\Throwable) {
            return response()->json(['count' => 0, 'items' => []]);
        }
        $items = $rows
            ->map(fn ($r) => [
                'id' => $r->id, 'company' => $r->opportunity->company->name, 'message' => $r->message,
                'page' => route('agent.whatsapp.show', $r), 'wa' => $r->waUrl(), 'key' => $r->id.':'.$r->nags,
            ]);

        return response()->json(['count' => $items->count(), 'items' => $items]);
    }
}
