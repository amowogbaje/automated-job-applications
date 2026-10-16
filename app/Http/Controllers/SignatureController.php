<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    public function index()
    {
        return view('signatures.index', ['signatures' => Signature::orderBy('channel')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('signatures.form', ['signature' => new Signature(['channel' => 'email'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $signature = Signature::create($this->validated($request));
        $this->afterSave($signature);

        return redirect()->route('signatures.index')->with('status', 'Signature added.');
    }

    public function edit(Signature $signature)
    {
        return view('signatures.form', compact('signature'));
    }

    public function update(Request $request, Signature $signature): RedirectResponse
    {
        $signature->update($this->validated($request));
        $this->afterSave($signature);

        return redirect()->route('signatures.index')->with('status', 'Signature updated.');
    }

    public function destroy(Signature $signature): RedirectResponse
    {
        $signature->delete();

        return redirect()->route('signatures.index')->with('status', 'Signature deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:email,whatsapp,form,any'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    /** Only one default per channel. */
    private function afterSave(Signature $signature): void
    {
        if ($signature->is_default) {
            Signature::where('id', '!=', $signature->id)
                ->where('channel', $signature->channel)
                ->update(['is_default' => false]);
        }
    }
}
