@extends('agent.layout')
@section('agent_title', 'Suggested services')
@section('agent')
<div class="mb-6">
    <a href="{{ route('agent.services.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Services</a>
    <h1 class="mt-1 text-2xl font-semibold">Services suggested from your Profile</h1>
    <p class="text-sm text-slate-500">Built only from the skills and projects already in your Profile. Tick the ones to keep; you can edit each afterwards.</p>
    @if ($result['note'])<p class="mt-2 text-sm text-amber-700">{{ $result['note'] }}</p>@endif
</div>

@if (! $result['services'])
    <div class="card p-8 text-center text-sm text-slate-500">Nothing new to suggest. Every offer found already exists as a service, or your Profile needs more skills.</div>
@else
<form method="POST" action="{{ route('agent.services.suggest.store') }}" class="space-y-3">
    @csrf
    @foreach ($result['services'] as $i => $s)
        <label class="card flex cursor-pointer items-start gap-3 p-4">
            <input type="checkbox" name="pick[]" value="{{ $i }}" class="mt-1" checked>
            <input type="hidden" name="items[{{ $i }}]" value="{{ json_encode($s, JSON_UNESCAPED_UNICODE) }}">
            <div class="min-w-0">
                <div class="font-medium">{{ $s['name'] }} <span class="badge bg-slate-100 text-slate-600">{{ $s['preferred_engagement'] }}</span></div>
                <p class="mt-1 text-sm text-slate-600">{{ $s['description'] }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($s['skills_required'] as $sk)<span class="badge bg-forest-50 text-forest-700">{{ $sk }}</span>@endforeach
                </div>
                @if ($s['search_keywords'])<p class="mt-2 text-xs text-slate-500">Searches for: {{ implode(' · ', $s['search_keywords']) }}</p>@endif
                @if ($s['project_names'])<p class="text-xs text-slate-500">Your projects: {{ implode(', ', $s['project_names']) }}</p>@endif
            </div>
        </label>
    @endforeach
    <button class="btn-primary">Add selected services</button>
</form>
@endif
@endsection
