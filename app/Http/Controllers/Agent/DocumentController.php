<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\SharedDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        return view('agent.documents.index', ['documents' => SharedDocument::latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,txt,csv'],
        ]);
        $f = $request->file('file');
        $path = $f->storeAs('shared-documents', bin2hex(random_bytes(8)).'.'.$f->getClientOriginalExtension(), 'local');
        SharedDocument::create([
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'path' => $path,
            'original_name' => $f->getClientOriginalName(), 'mime' => $f->getClientMimeType(), 'size' => $f->getSize(),
            'attach_to_emails' => $request->boolean('attach_to_emails'),
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    public function toggle(SharedDocument $document): RedirectResponse
    {
        $document->update(['attach_to_emails' => ! $document->attach_to_emails]);

        return back()->with('status', $document->attach_to_emails ? 'Will be included in automatic follow-up emails.' : 'No longer included in automatic emails.');
    }

    public function destroy(SharedDocument $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document deleted. Links you shared before will stop working.');
    }
}
