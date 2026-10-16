@extends('layouts.app')
@section('title', $project->exists ? 'Edit project' : 'New project')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">{{ $project->exists ? 'Edit project' : 'New project' }}</h1>
<form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="card space-y-4 p-5">
    @csrf
    @if ($project->exists) @method('PUT') @endif
    <div>
        <label class="label" for="name">Name</label>
        <input id="name" name="name" class="input" required value="{{ old('name', $project->name) }}">
    </div>
    <div>
        <label class="label" for="url">URL</label>
        <input id="url" name="url" type="url" class="input" placeholder="https://" value="{{ old('url', $project->url) }}">
    </div>
    <div>
        <label class="label" for="summary">Summary</label>
        <textarea id="summary" name="summary" rows="3" class="input">{{ old('summary', $project->summary) }}</textarea>
    </div>
    <x-tag-input name="technologies" label="Technologies" :values="$project->technologies ?? []" />
    <x-tag-input name="industries" label="Industries" :values="$project->industries ?? []" />
    <div>
        <label class="label" for="proof_points">Proof points</label>
        <textarea id="proof_points" name="proof_points" rows="3" class="input" placeholder="Concrete, true facts the agent may cite (users, features, results).">{{ old('proof_points', $project->proof_points) }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $project->is_public ?? true))> Public (safe to link in outreach)</label>
    <div class="flex gap-2">
        <button class="btn-primary">Save project</button>
        <a href="{{ route('projects.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
