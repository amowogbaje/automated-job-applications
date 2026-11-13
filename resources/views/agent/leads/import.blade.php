@extends('agent.layout')
@section('agent_title', 'Upload leads')
@section('agent')
<div class="mb-6"><a href="{{ route('agent.leads.index') }}" class="text-sm text-slate-500 hover:underline">← Leads</a>
    <h1 class="text-2xl font-semibold">Upload leads</h1>
    <p class="text-sm text-slate-500">These are leads you found yourself, so search is skipped. You review everything before it is saved.</p></div>

<div class="mb-6 card p-5 text-sm">
    <h2 class="mb-2 font-medium">The format</h2>
    <p class="mb-2 text-slate-600">A CSV with these columns (only <strong>company</strong> is required; use at least an email or phone to be followed up):</p>
    <code class="block overflow-x-auto rounded bg-slate-100 p-2 text-xs">{{ implode(', ', $fields) }}</code>
    <p class="mt-2"><a href="{{ route('agent.leads.template') }}" class="text-forest-600 hover:underline">Download the template</a></p>
</div>

<form method="POST" action="{{ route('agent.leads.preview') }}" enctype="multipart/form-data" class="card space-y-4 p-5">
    @csrf
    <div><label class="label">CSV file</label><input type="file" name="file" accept=".csv,.txt" class="input"></div>
    <div class="text-center text-xs text-slate-400">or</div>
    <div>
        <label class="label">Paste raw text</label>
        <textarea name="raw" rows="9" class="input" placeholder="Paste a CSV, or anything messy: notes, a WhatsApp list, business cards typed out...">{{ old('raw') }}</textarea>
        <p class="mt-1 text-xs text-slate-500">{{ $aiReady ? 'Text that is not in the CSV format is organised by AI into the format. It never invents emails or phone numbers.' : 'No AI provider is active, so only the CSV format works. Add a key on the Providers page to organise raw text.' }}</p>
    </div>
    <button class="btn-primary">Preview</button>
</form>
@endsection
