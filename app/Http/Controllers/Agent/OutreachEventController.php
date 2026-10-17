<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\ActivityLog;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutreachEventController extends Controller
{
    /** Manual log of what happened (sent, reply, call...). Nothing is ever sent from here. */
    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'event_type' => ['required', Rule::in(array_keys(config('opportunity.event_types')))],
            'channel' => ['nullable', Rule::in(array_keys(config('opportunity.channels')))],
            'notes' => ['nullable', 'string', 'max:3000'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $data['occurred_at'] = $data['occurred_at'] ?? now();

        $opportunity->outreachEvents()->create($data);

        // Keep the pipeline honest with what actually happened.
        if ($data['event_type'] === 'sent' && in_array($opportunity->status, ['discovered', 'researching', 'qualified', 'drafted', 'approved'], true)) {
            $opportunity->update(['status' => 'contacted', 'contacted_at' => $opportunity->contacted_at ?? $data['occurred_at']]);
        } elseif ($data['event_type'] === 'reply' && in_array($opportunity->status, ['contacted', 'approved'], true)) {
            $opportunity->update(['status' => 'replied']);
        }

        ActivityLog::record($opportunity, 'event_logged', config('opportunity.event_types')[$data['event_type']]);

        return back()->with('status', 'Logged.');
    }
}
