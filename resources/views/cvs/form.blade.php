@extends('layouts.app')
@section('title', $cv->exists ? 'Edit CV' : 'New CV')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">{{ $cv->exists ? 'Edit CV version' : 'New CV version' }}</h1>
<form method="POST" enctype="multipart/form-data" action="{{ $cv->exists ? route('cvs.update', $cv) : route('cvs.store') }}" class="card space-y-4 p-5">
    @csrf
    @if ($cv->exists) @method('PUT') @endif
    <div>
        <label class="label" for="name">Name</label>
        <input id="name" name="name" class="input" required placeholder="e.g. Backend / Laravel CV" value="{{ old('name', $cv->name) }}">
    </div>
    <div>
        <label class="label" for="description">When to use it</label>
        <textarea id="description" name="description" rows="3" class="input">{{ old('description', $cv->description) }}</textarea>
    </div>
    <div>
        <label class="label" for="file">File (PDF or Word, max 5 MB)</label>
        <input id="file" name="file" type="file" accept=".pdf,.doc,.docx" class="block w-full text-sm">
        @if ($cv->file_path)<p class="mt-1 text-xs text-slate-500">A file is stored. Uploading another replaces it.</p>@endif
    </div>
    <div>
        <label class="label" for="external_url">Or external link</label>
        <input id="external_url" name="external_url" type="url" class="input" placeholder="https://" value="{{ old('external_url', $cv->external_url) }}">
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $cv->is_default))> Default CV (used when nothing is a better match)</label>
    <div class="flex gap-2">
        <button class="btn-primary">Save CV</button>
        <a href="{{ route('cvs.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
