@extends('agent.layout')
@section('agent_title', 'Documents')
@section('agent')
<div class="mb-6"><h1 class="text-2xl font-semibold">Documents</h1>
    <p class="text-sm text-slate-500">Files you want leads to see: a rate card, your services, a portfolio PDF. Documents marked "include in emails" are linked and attached to automatic follow-ups.</p></div>

<form method="POST" action="{{ route('agent.documents.store') }}" enctype="multipart/form-data" class="card mb-6 grid gap-3 p-5 sm:grid-cols-2">
    @csrf
    <div><label class="label">Title</label><input name="title" required class="input" placeholder="Rate card"></div>
    <div><label class="label">File (PDF, Word, Excel, image; max 10 MB)</label><input type="file" name="file" required class="input"></div>
    <div class="sm:col-span-2"><label class="label">Description</label><input name="description" class="input"></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="attach_to_emails" value="1"> Include in automatic follow-up emails</label>
    <div class="text-right"><button class="btn-primary">Upload</button></div>
</form>

<div class="card divide-y divide-slate-100">
    @forelse ($documents as $d)
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
            <div class="min-w-0"><div class="font-medium">{{ $d->title }} <span class="text-xs font-normal text-slate-500">{{ $d->original_name }} · {{ $d->sizeLabel() }}</span></div>
                @if ($d->description)<div class="text-xs text-slate-500">{{ $d->description }}</div>@endif
                <input readonly value="{{ $d->publicUrl() }}" onclick="this.select()" class="input mt-1 py-1 text-xs"></div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('agent.documents.toggle', $d) }}">@csrf @method('PATCH')<button class="badge {{ $d->attach_to_emails ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $d->attach_to_emails ? 'In emails' : 'Not in emails' }}</button></form>
                <form method="POST" action="{{ route('agent.documents.destroy', $d) }}" onsubmit="return confirm('Delete? Links you shared stop working.')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete</button></form>
            </div>
        </div>
    @empty
        <p class="p-6 text-center text-sm text-slate-500">No documents yet.</p>
    @endforelse
</div>
@endsection
