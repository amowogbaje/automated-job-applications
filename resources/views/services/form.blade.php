@extends('layouts.app')
@section('title', $service->exists ? 'Edit service' : 'New service')
@section('content')
<h1 class="mb-6 text-2xl font-semibold">{{ $service->exists ? 'Edit service' : 'New service' }}</h1>

<form method="POST" action="{{ $service->exists ? route('services.update', $service) : route('services.store') }}" class="space-y-6">
    @csrf
    @if ($service->exists) @method('PUT') @endif

    <div class="card space-y-4 p-5">
        <div>
            <label class="label" for="name">Service name</label>
            <input id="name" name="name" class="input" required value="{{ old('name', $service->name) }}">
        </div>
        <div>
            <label class="label" for="description">Description</label>
            <textarea id="description" name="description" rows="3" class="input">{{ old('description', $service->description) }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="preferred_engagement">Preferred engagement</label>
                <select id="preferred_engagement" name="preferred_engagement" class="input">
                    @foreach (['any', 'employment', 'contract', 'project', 'consulting'] as $opt)
                        <option value="{{ $opt }}" @selected(old('preferred_engagement', $service->preferred_engagement ?? 'any') === $opt)>{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="minimum_budget">Minimum budget</label>
                <input id="minimum_budget" name="minimum_budget" type="number" min="0" class="input" value="{{ old('minimum_budget', $service->minimum_budget) }}">
            </div>
            <div>
                <label class="label" for="budget_currency">Currency</label>
                <input id="budget_currency" name="budget_currency" maxlength="3" class="input uppercase" value="{{ old('budget_currency', $service->budget_currency ?? 'USD') }}">
            </div>
        </div>
        <div class="flex flex-wrap gap-6 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> Active</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_employment" value="1" @checked(old('is_employment', $service->is_employment))> This is an employment category</label>
            <label class="flex items-center gap-2">Sort order
                <input type="number" name="sort_order" min="0" class="input w-20" value="{{ old('sort_order', $service->sort_order ?? 0) }}">
            </label>
        </div>
    </div>

    <div class="card space-y-4 p-5">
        <h2 class="font-medium">Matching inputs</h2>
        <x-tag-input name="skills_required" label="Skills required" :values="$service->skills_required ?? []" />
        <x-tag-input name="search_keywords" label="Search keywords" :values="$service->search_keywords ?? []" hint="Phrases the discovery engine will search for." />
        <x-tag-input name="target_businesses" label="Target businesses" :values="$service->target_businesses ?? []" hint="e.g. logistics SMEs, consultancies, fintech startups." />
        <x-tag-input name="opportunity_signals" label="Opportunity signals" :values="$service->opportunity_signals ?? []" hint="Evidence that suggests a real need, e.g. hiring a Laravel developer." />
        <x-tag-input name="geographic_preferences" label="Geographic preferences" :values="$service->geographic_preferences ?? []" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <h2 class="mb-3 font-medium">Relevant projects</h2>
            @forelse ($projects as $project)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0"
                     x-data="{ on: {{ array_key_exists($project->id, $linkedProjects) ? 'true' : 'false' }} }">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="project_ids[]" value="{{ $project->id }}" x-model="on"> {{ $project->name }}
                    </label>
                    <select name="relevance[{{ $project->id }}]" x-show="on" x-cloak class="rounded border border-slate-300 px-1 py-0.5 text-xs">
                        @foreach ([5 => '5 · core', 4 => '4', 3 => '3', 2 => '2', 1 => '1 · weak'] as $v => $l)
                            <option value="{{ $v }}" @selected(($linkedProjects[$project->id] ?? 3) == $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            @empty
                <p class="text-sm text-slate-500">No projects yet. <a class="text-indigo-600 hover:underline" href="{{ route('projects.create') }}">Add one</a>.</p>
            @endforelse
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-medium">Relevant CV versions</h2>
            @forelse ($cvs as $cv)
                <label class="flex items-center gap-2 border-b border-slate-100 py-2 text-sm last:border-0">
                    <input type="checkbox" name="cv_ids[]" value="{{ $cv->id }}" @checked(in_array($cv->id, $linkedCvs))> {{ $cv->name }}
                </label>
            @empty
                <p class="text-sm text-slate-500">No CVs yet. <a class="text-indigo-600 hover:underline" href="{{ route('cvs.create') }}">Add one</a>.</p>
            @endforelse
        </div>
    </div>

    <div class="flex gap-2">
        <button class="btn-primary">Save service</button>
        <a href="{{ route('services.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
