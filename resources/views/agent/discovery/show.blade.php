@extends('agent.layout')
@section('agent_title', 'Discovery run')
@section('agent')
@php
    $badge = ['pending' => 'bg-slate-100 text-slate-600', 'running' => 'bg-sky-100 text-sky-700', 'completed' => 'bg-green-100 text-green-700', 'failed' => 'bg-red-100 text-red-700'];
    $result = $run->result ?? [];
@endphp
<div x-data="discoveryPanel(@js($payload))">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('agent.discovery.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Discovery</a>
            <h1 class="mt-1 text-2xl font-semibold">{{ $strategy['label'] }}
                <span class="badge align-middle {{ $badge[$run->status] ?? $badge['pending'] }}" x-text="run.status">{{ $run->status }}</span>
            </h1>
            <p class="text-sm text-slate-500">{{ $run->service?->name ?? 'Own queries' }} · started {{ ($run->started_at ?? $run->created_at)->diffForHumans() }}</p>
        </div>
    </div>

    <p x-show="active()" x-cloak class="card mb-6 p-4 text-sm text-slate-600">Searching and reading results. This page updates when it finishes.</p>

    @if ($run->status === 'failed')
        <div class="card mb-6 border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $run->error }}</div>
    @endif

    @if (in_array($run->status, ['completed', 'failed'], true))
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card p-4 text-center"><div class="text-2xl font-semibold">{{ $run->candidates_new }}</div><div class="text-xs text-slate-500">new candidates</div></div>
            <div class="card p-4 text-center"><div class="text-2xl font-semibold">{{ $result['seen'] ?? 0 }}</div><div class="text-xs text-slate-500">results read</div></div>
            <div class="card p-4 text-center"><div class="text-2xl font-semibold">{{ $result['known'] ?? 0 }}</div><div class="text-xs text-slate-500">already known</div></div>
            <div class="card p-4 text-center"><div class="text-2xl font-semibold">{{ array_sum($result['rejected'] ?? []) }}</div><div class="text-xs text-slate-500">dropped as unverified</div></div>
        </div>
    @endif

    <div class="mb-6 space-y-3">
        @forelse ($run->opportunities as $o)
            <div class="card p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('agent.opportunities.show', $o) }}" class="font-medium hover:text-indigo-700">{{ $o->company->name }} · {{ $o->title }}</a>
                        <p class="mt-1 text-sm text-slate-600">{{ $o->summary }}</p>
                        @if ($o->source_url)<a href="{{ $o->source_url }}" target="_blank" rel="noopener" class="mt-1 block truncate text-xs text-indigo-600 hover:underline">{{ $o->source_url }}</a>@endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="badge bg-slate-100 text-slate-600">{{ $o->confidence }} confidence</span>
                        <form method="POST" action="{{ route('agent.research.start', $o) }}">@csrf<button class="btn-secondary">Research</button></form>
                    </div>
                </div>
            </div>
        @empty
            @if ($run->status === 'completed')<p class="text-sm text-slate-500">Nothing new this time. Try other strategies, different keywords on the service, or your own queries.</p>@endif
        @endforelse
    </div>

    @if ($run->queries)
        <details class="card p-4 text-sm">
            <summary class="cursor-pointer font-medium text-slate-700">What was searched and dropped</summary>
            <ul class="mt-3 list-inside list-disc text-slate-600">@foreach ($run->queries as $q)<li>{{ $q }}</li>@endforeach</ul>
            @if (! empty($result['rejected']))
                <p class="mt-3 font-medium text-slate-700">Dropped:</p>
                <ul class="list-inside list-disc text-slate-600">@foreach ($result['rejected'] as $why => $n)<li>{{ $n }} × {{ $why }}</li>@endforeach</ul>
            @endif
            @foreach ($result['notes'] ?? [] as $note)<p class="mt-2 text-amber-700">{{ $note }}</p>@endforeach
        </details>
    @endif
</div>
@endsection
