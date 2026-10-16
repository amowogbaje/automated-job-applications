@extends('layouts.app')
@section('title', 'CVs')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">CV versions</h1>
        <p class="text-sm text-slate-500">Different CVs for different services.</p>
    </div>
    <a href="{{ route('cvs.create') }}" class="btn-primary">Add CV</a>
</div>
<div class="space-y-3">
    @forelse ($cvs as $cv)
        <div class="card flex items-start justify-between gap-4 p-4">
            <div class="min-w-0">
                <a href="{{ route('cvs.edit', $cv) }}" class="font-medium hover:text-indigo-600">{{ $cv->name }}</a>
                @if ($cv->is_default)<span class="badge ml-2 bg-green-100 text-green-700">default</span>@endif
                <p class="mt-1 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($cv->description, 140) }}</p>
                <div class="mt-1 flex gap-3 text-xs">
                    @if ($cv->file_path)<a href="{{ route('cvs.download', $cv) }}" class="text-indigo-600 hover:underline">Download file</a>@endif
                    @if ($cv->external_url)<a href="{{ $cv->external_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">External link</a>@endif
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <a href="{{ route('cvs.edit', $cv) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('cvs.destroy', $cv) }}" onsubmit="return confirm('Delete this CV version?')">
                    @csrf @method('DELETE')<button class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No CV versions yet.</div>
    @endforelse
</div>
@endsection
