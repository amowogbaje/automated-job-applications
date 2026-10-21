@extends('agent.layout')
@section('agent_title', $project->exists ? 'Edit project' : 'New project')
@section('agent')
<h1 class="mb-6 text-2xl font-semibold">{{ $project->exists ? 'Edit project' : 'New project' }}</h1>
@if ($project->isFromProfile())
    <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-800">This project comes from your Profile, so its name, link, summary and technologies are edited there. <a href="{{ route('profile.edit', ['tab' => 'profile']) }}#projects" class="font-medium underline">Edit in Profile</a>. Industries, proof points and visibility are edited here.</div>
@endif
<form method="POST" action="{{ $project->exists ? route('agent.projects.update', $project) : route('agent.projects.store') }}" class="card space-y-4 p-5">
    @csrf
    @if ($project->exists) @method('PUT') @endif
    <div>
        <label class="label" for="name">Name</label>
        <input id="name" name="name" class="input" required @readonly($project->isFromProfile()) value="{{ old('name', $project->name) }}">
    </div>
    <div>
        <label class="label" for="url">URL</label>
        <input id="url" name="url" type="url" class="input" @readonly($project->isFromProfile()) placeholder="https://" value="{{ old('url', $project->url) }}">
    </div>
    <div>
        <label class="label" for="summary">Summary</label>
        <textarea id="summary" name="summary" rows="3" class="input" @readonly($project->isFromProfile())>{{ old('summary', $project->summary) }}</textarea>
    </div>
    <x-agent.tag-input name="technologies" label="Technologies" :values="$project->technologies ?? []" />
    <x-agent.tag-input name="industries" label="Industries" :values="$project->industries ?? []" />
    <div>
        <label class="label" for="proof_points">Proof points</label>
        <textarea id="proof_points" name="proof_points" rows="3" class="input" placeholder="Concrete, true facts the agent may cite (users, features, results).">{{ old('proof_points', $project->proof_points) }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $project->is_public ?? true))> Public (safe to link in outreach)</label>
    <div class="flex gap-2">
        <button class="btn-primary">Save project</button>
        <a href="{{ route('agent.projects.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
