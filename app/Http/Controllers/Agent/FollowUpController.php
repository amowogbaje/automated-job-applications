<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\ActivityLog;
use App\Models\FollowUp;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $opportunity->followUps()->create($data);
        ActivityLog::record($opportunity, 'follow_up_added', 'Follow-up set: '.$data['title']);

        return back()->with('status', 'Follow-up added.');
    }

    public function complete(FollowUp $followUp): RedirectResponse
    {
        $followUp->update(['completed_at' => $followUp->completed_at ? null : now()]);

        return back()->with('status', $followUp->completed_at ? 'Marked done.' : 'Reopened.');
    }

    public function destroy(FollowUp $followUp): RedirectResponse
    {
        $followUp->delete();

        return back()->with('status', 'Follow-up removed.');
    }
}
