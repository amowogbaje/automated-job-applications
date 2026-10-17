<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\ActivityLog;
use App\Models\Evidence;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvidenceController extends Controller
{
    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(config('opportunity.evidence_kinds')))],
            'source_url' => ['required', 'url', 'max:2048'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'finding' => ['required', 'string', 'max:3000'],
            'establishes' => ['nullable', 'string', 'max:3000'],
            'inference' => ['nullable', 'string', 'max:3000'],
            'confidence' => ['required', Rule::in(['low', 'moderate', 'high'])],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'retrieved_at' => ['nullable', 'date'],
        ]);

        $data['retrieved_at'] = $data['retrieved_at'] ?? now();

        $opportunity->evidence()->create($data + ['company_id' => $opportunity->company_id]);
        ActivityLog::record($opportunity, 'evidence_added', 'Evidence added: '.str($data['finding'])->limit(80));

        return back()->with('status', 'Evidence saved.');
    }

    public function destroy(Evidence $evidence): RedirectResponse
    {
        $evidence->delete();

        return back()->with('status', 'Evidence removed.');
    }
}
