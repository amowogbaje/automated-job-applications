@extends('layouts.app')
@section('title', 'Signatures')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Signatures</h1>
        <p class="text-sm text-slate-500">Appended to drafted emails and messages.</p>
    </div>
    <a href="{{ route('signatures.create') }}" class="btn-primary">Add signature</a>
</div>
<div class="space-y-3">
    @forelse ($signatures as $sig)
        <div class="card flex items-start justify-between gap-4 p-4">
            <div class="min-w-0">
                <a href="{{ route('signatures.edit', $sig) }}" class="font-medium hover:text-indigo-600">{{ $sig->name }}</a>
                <span class="badge ml-2 bg-slate-100 text-slate-600">{{ $sig->channel }}</span>
                @if ($sig->is_default)<span class="badge ml-1 bg-green-100 text-green-700">default</span>@endif
                <pre class="mt-2 whitespace-pre-wrap font-sans text-sm text-slate-600">{{ $sig->body }}</pre>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <a href="{{ route('signatures.edit', $sig) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('signatures.destroy', $sig) }}" onsubmit="return confirm('Delete this signature?')">
                    @csrf @method('DELETE')<button class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No signatures yet.</div>
    @endforelse
</div>
@endsection
