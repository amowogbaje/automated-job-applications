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
            @if (! empty($result['contacts']))<div class="card p-4 text-center"><div class="text-2xl font-semibold">{{ $result['contacts'] }}</div><div class="text-xs text-slate-500">contact routes found</div></div>@endif
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
                        @if ($o->work_mode)<span class="badge bg-sky-100 text-sky-700">{{ $o->work_mode }}</span>@endif
                        @if ($o->web_presence)<span class="badge bg-slate-100 text-slate-600">{{ ['website' => 'has website', 'social_only' => 'social page only', 'unknown' => 'web presence unknown'][$o->web_presence] ?? $o->web_presence }}</span>@endif
                        @if ($o->type === 'prospect')<span class="badge bg-violet-100 text-violet-700">prospect</span>@endif
                        <form method="POST" action="{{ route('agent.research.start', $o) }}">@csrf<button class="btn-secondary">Research</button></form>
                    </div>
                </div>
            </div>
        @empty
            @if ($run->status === 'completed')<p class="text-sm text-slate-500">Nothing new this time. Try other strategies, different keywords on the service, or your own queries.</p>@endif
        @endforelse
    </div>

    @php $trace = $result['trace'] ?? null; @endphp
    @if ($trace && (! empty($trace['searches']) || ! empty($trace['ai'])))
        <div class="card mb-6 p-4 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium text-slate-800">Full trace: every search result and what happened to it</h2>
                <a href="{{ route('agent.discovery.trace', $run) }}" class="btn-secondary">Download JSON</a>
            </div>
            <p class="mt-1 text-xs text-slate-500">Step 1: each query and the raw results the search provider returned. Step 2: what the AI proposed from them and why each proposal was kept or dropped.</p>

            <h3 class="mt-4 font-medium text-slate-700">1. Searches</h3>
            @foreach ($trace['searches'] as $i => $s)
                <details class="mt-2 rounded border border-slate-200 p-3" @if ($i === 0) open @endif>
                    <summary class="cursor-pointer text-slate-700">
                        <span class="font-medium">{{ $s['query'] }}</span>
                        <span class="text-xs text-slate-500">· {{ $s['provider'] ?? 'failed' }} · {{ count($s['results']) }} result(s)</span>
                    </summary>
                    @if ($s['error'])<p class="mt-2 text-red-700">{{ $s['error'] }}</p>@endif
                    <ol class="mt-2 space-y-3">
                        @foreach ($s['results'] as $r)
                            <li>
                                <a href="{{ $r['url'] }}" target="_blank" rel="noopener" class="font-medium text-indigo-700 hover:underline">{{ $r['title'] ?: $r['url'] }}</a>
                                <div class="truncate text-xs text-slate-400">{{ $r['url'] }}</div>
                                <p class="text-slate-600">{{ $r['snippet'] }}</p>
                                <span class="badge {{ str_starts_with($r['fate'], 'sent') ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $r['fate'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endforeach

            <h3 class="mt-5 font-medium text-slate-700">2. What the AI proposed</h3>
            @forelse ($trace['ai'] as $b)
                <div class="mt-2 rounded border border-slate-200 p-3">
                    <p class="text-xs text-slate-500">{{ $b['provider'] }} · {{ $b['model'] }} · {{ number_format($b['tokens']) }} tokens · read {{ implode(', ', $b['sources']) }}</p>
                    @forelse ($b['candidates'] as $c)
                        <div class="mt-3">
                            <div class="font-medium text-slate-800">{{ $c['company'] ?: '(no name)' }} <span class="font-normal text-slate-500">· {{ $c['source'] }}</span></div>
                            <p class="text-slate-600">{{ $c['finding'] }}</p>
                            @if ($c['excerpt'])<p class="border-l-2 border-slate-300 pl-2 text-xs italic text-slate-500">"{{ $c['excerpt'] }}"</p>@endif
                            <span class="badge {{ str_starts_with($c['verdict'], 'saved') ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">{{ $c['verdict'] }}</span>
                        </div>
                    @empty
                        <p class="mt-2 text-slate-500">The AI proposed no candidates from these results.</p>
                    @endforelse
                </div>
            @empty
                <p class="mt-2 text-slate-500">The AI was not called (no usable new results).</p>
            @endforelse
        </div>
    @endif

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
