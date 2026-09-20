<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDraft;
use Illuminate\Http\Request;

class ApplicationDraftController extends Controller
{
    public function index()
    {
        $drafts = ApplicationDraft::with('job')
            ->whereIn('status', ['draft', 'ready'])
            ->latest()
            ->paginate(20);

        return view('drafts.index', compact('drafts'));
    }

    public function update(Request $request, ApplicationDraft $draft)
    {
        $data = $request->validate([
            'cover_letter' => 'required|string',
        ]);

        $draft->update(['cover_letter' => $data['cover_letter']]);

        return back()->with('status', 'Draft updated.');
    }

    // You click this AFTER you've personally reviewed and sent the application
    // elsewhere (email, the platform's own apply button, etc). This never
    // submits anything on your behalf — it just tracks that you've applied.
    public function markSent(ApplicationDraft $draft)
    {
        $draft->update(['status' => 'sent']);
        $draft->job()->update(['is_applied' => true]);

        return back()->with('status', 'Marked as sent.');
    }

    public function discard(ApplicationDraft $draft)
    {
        $draft->update(['status' => 'discarded']);

        return back();
    }
}
