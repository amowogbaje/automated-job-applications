<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDraft;
use App\Models\JobListingUserState;
use Illuminate\Http\Request;

class ApplicationDraftController extends Controller
{
    public function index(Request $request)
    {
        $drafts = ApplicationDraft::with('job')
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'ready'])
            ->latest()
            ->paginate(20);

        return view('drafts.index', compact('drafts'));
    }

    public function update(Request $request, ApplicationDraft $draft)
    {
        $this->authorizeOwner($draft);

        $data = $request->validate([
            'cover_letter' => 'required|string',
        ]);

        $draft->update(['cover_letter' => $data['cover_letter']]);

        return back()->with('status', 'Draft updated.');
    }

    // You click this AFTER you've personally reviewed and sent the application
    // elsewhere (email, the platform's own apply button, etc). This never
    // submits anything on your behalf — it just tracks that you've applied.
    public function markSent(Request $request, ApplicationDraft $draft)
    {
        $this->authorizeOwner($draft);

        $draft->update(['status' => 'sent']);

        JobListingUserState::updateOrCreate(
            ['job_listing_id' => $draft->job_listing_id, 'user_id' => $request->user()->id],
            ['is_applied' => true, 'applied_at' => now()]
        );

        return back()->with('status', 'Marked as sent.');
    }

    public function discard(Request $request, ApplicationDraft $draft)
    {
        $this->authorizeOwner($draft);

        $draft->update(['status' => 'discarded']);

        return back();
    }

    private function authorizeOwner(ApplicationDraft $draft): void
    {
        abort_unless($draft->user_id === request()->user()->id, 403);
    }
}
