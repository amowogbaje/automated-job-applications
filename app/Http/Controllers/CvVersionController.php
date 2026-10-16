<?php

namespace App\Http\Controllers;

use App\Models\CvVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CvVersionController extends Controller
{
    public function index()
    {
        return view('cvs.index', ['cvs' => CvVersion::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('cvs.form', ['cv' => new CvVersion]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cv = CvVersion::create($this->payload($request));
        $this->afterSave($cv);

        return redirect()->route('cvs.index')->with('status', 'CV version added.');
    }

    public function edit(CvVersion $cv)
    {
        return view('cvs.form', compact('cv'));
    }

    public function update(Request $request, CvVersion $cv): RedirectResponse
    {
        $cv->update($this->payload($request, $cv));
        $this->afterSave($cv);

        return redirect()->route('cvs.index')->with('status', 'CV version updated.');
    }

    public function destroy(CvVersion $cv): RedirectResponse
    {
        if ($cv->file_path) {
            Storage::disk('local')->delete($cv->file_path);
        }
        $cv->delete();

        return redirect()->route('cvs.index')->with('status', 'CV version deleted.');
    }

    /** Files live on the private disk; they are only served to the logged-in user. */
    public function download(CvVersion $cv)
    {
        abort_unless($cv->file_path && Storage::disk('local')->exists($cv->file_path), 404);

        return Storage::disk('local')->download($cv->file_path);
    }

    private function payload(Request $request, ?CvVersion $cv = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'external_url' => ['nullable', 'url', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);
        unset($data['file']);
        $data['is_default'] = $request->boolean('is_default');

        if ($request->hasFile('file')) {
            if ($cv?->file_path) {
                Storage::disk('local')->delete($cv->file_path);
            }
            $data['file_path'] = $request->file('file')->store('cvs', 'local');
        }

        return $data;
    }

    private function afterSave(CvVersion $cv): void
    {
        if ($cv->is_default) {
            CvVersion::where('id', '!=', $cv->id)->update(['is_default' => false]);
        }
    }
}
