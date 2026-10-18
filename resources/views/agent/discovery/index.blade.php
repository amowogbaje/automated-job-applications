@extends('agent.layout')
@section('agent_title', 'Discovery')
@section('agent')
@php
    $badge = ['pending' => 'bg-slate-100 text-slate-600', 'running' => 'bg-sky-100 text-sky-700', 'completed' => 'bg-green-100 text-green-700', 'failed' => 'bg-red-100 text-red-700'];
@endphp
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Discovery</h1>
    <p class="text-sm text-slate-500">Find companies that might need your services. Everything found is saved as <strong>Discovered</strong> with a quoted source. Nothing is contacted, and nothing is treated as a real need until you research it.</p>
</div>

@if (! $aiReady || ! $searchReady)
    <div class="card mb-6 border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Discovery needs {{ ! $aiReady && ! $searchReady ? 'an AI provider and a search provider' : (! $aiReady ? 'an AI provider' : 'a search provider') }} with a key.
        <a href="{{ route('agent.providers.index') }}" class="font-medium underline">Open Providers</a>.
    </div>
@endif

<div class="grid items-start gap-6 lg:grid-cols-[1fr_340px]">
    <form method="POST" action="{{ route('agent.discovery.store') }}" class="card space-y-5 p-6" x-data="{ picked: @js(old('strategies', ['hiring'])) }">
        @csrf
        <div>
            <label class="label">Where to look</label>
            <div class="space-y-2">
                @foreach ($strategies as $key => $s)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:border-indigo-300" x-bind:class="picked.includes('{{ $key }}') && 'border-indigo-400 bg-indigo-50/50'">
                        <input type="checkbox" name="strategies[]" value="{{ $key }}" x-model="picked" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span><span class="block text-sm font-medium">{{ $s['label'] }}</span><span class="block text-xs text-slate-500">{{ $s['about'] }}</span></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label for="service_id" class="label">For which service</label>
            <select id="service_id" name="service_id" class="input">
                <option value="">None (I'll write my own queries)</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Queries are built from the service's keywords, skills, target businesses, signals and regions. Up to {{ $maxQueries }} searches per run.</p>
        </div>

        <div>
            <label for="extra_queries" class="label">My own search queries (optional, one per line)</label>
            <textarea id="extra_queries" name="extra_queries" rows="3" class="input" placeholder="laravel developer contract fintech UK">{{ old('extra_queries') }}</textarea>
        </div>

        <label class="flex items-start gap-3">
            <input type="checkbox" name="auto_research" value="1" @checked(old('auto_research')) class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span><span class="block text-sm font-medium">Research the new candidates automatically</span><span class="block text-xs text-slate-500">Queues the research agent for up to {{ config('discovery.auto_research_cap') }} of them. Uses more AI and search quota. Needs the queue cron.</span></span>
        </label>

        <div class="flex items-center gap-3">
            <button class="btn-primary" @disabled(! $aiReady || ! $searchReady)>Find candidates</button>
            <span class="text-xs text-slate-500">{{ $runsToday }} of {{ $dailyCap }} runs used today.</span>
        </div>
    </form>

    <div class="card p-5">
        <h2 class="mb-3 font-medium">Recent runs</h2>
        @forelse ($runs as $r)
            <a href="{{ route('agent.discovery.show', $r) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0 hover:text-indigo-700">
                <span class="min-w-0">
                    <span class="block truncate font-medium">{{ $strategies[$r->strategy]['label'] ?? $r->strategy }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ $r->service?->name ?? 'own queries' }} · {{ $r->created_at->diffForHumans() }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    @if ($r->status === 'completed')<span class="text-xs text-slate-500">{{ $r->candidates_new }} new</span>@endif
                    <span class="badge {{ $badge[$r->status] ?? $badge['pending'] }}">{{ $r->status }}</span>
                </span>
            </a>
        @empty
            <p class="text-sm text-slate-500">No runs yet.</p>
        @endforelse
    </div>
</div>
@endsection
