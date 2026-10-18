@extends('agent.layout')
@section('agent_title', 'Dashboard')
@section('agent')
@include('partials.dash-tabs', ['active' => 'overview'])
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Dashboard</h1>
    <a href="{{ route('agent.opportunities.create') }}" class="btn-primary">Add opportunity</a>
</div>

{{-- Pipeline --}}
<div class="mb-8 grid grid-cols-3 gap-3 sm:grid-cols-6 lg:grid-cols-9">
    @foreach (collect(config('opportunity.statuses'))->except(['rejected', 'archived']) as $key => $label)
        <a href="{{ route('agent.opportunities.index', ['status' => $key]) }}" class="card p-3 text-center hover:border-forest-500">
            <div class="text-2xl font-semibold">{{ $pipeline[$key] ?? 0 }}</div>
            <div class="text-xs text-slate-500">{{ $label }}</div>
        </a>
    @endforeach
</div>

<div class="mb-8 grid gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-3 font-medium">Top opportunities</h2>
        @forelse ($topOpportunities as $o)
            <a href="{{ route('agent.opportunities.show', $o) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0 hover:text-forest-600">
                <span class="min-w-0 truncate"><span class="font-medium">{{ $o->company->name }}</span> · {{ $o->title }}</span>
                <span class="flex shrink-0 items-center gap-2"><x-agent.priority-badge :priority="$o->priority" /><span class="text-slate-500">{{ $o->total_score }}</span></span>
            </a>
        @empty
            <p class="text-sm text-slate-500">Nothing scored yet. Open an opportunity and fill in its assessment.</p>
        @endforelse
    </div>

    <div class="card p-5">
        <h2 class="mb-3 font-medium">Follow-ups (next 7 days)</h2>
        @forelse ($followUps as $f)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                <a href="{{ route('agent.opportunities.show', $f->opportunity) }}" class="min-w-0 truncate hover:text-forest-600">
                    <span class="font-medium">{{ $f->title }}</span> · {{ $f->opportunity->company->name }}
                </a>
                <span class="flex shrink-0 items-center gap-2">
                    <span class="{{ $f->due_at->isPast() ? 'text-red-600 font-medium' : 'text-slate-500' }}">{{ $f->due_at->format('M j, H:i') }}</span>
                    <form method="POST" action="{{ route('agent.follow-ups.complete', $f) }}">@csrf @method('PATCH')<button class="text-xs text-forest-600 hover:underline">Done</button></form>
                </span>
            </div>
        @empty
            <p class="text-sm text-slate-500">No follow-ups due.</p>
        @endforelse
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-3 font-medium">Setup checklist</h2>
        <p class="mb-4 text-sm text-slate-500">The agent needs these before it can match opportunities and draft outreach.</p>
        <ul class="space-y-2">
            @foreach ($setup as [$label, $done, $url])
                <li class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-xs {{ $done ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-400' }}">{{ $done ? '✓' : '·' }}</span>
                        <span class="{{ $done ? 'text-slate-500 line-through' : '' }}">{{ $label }}</span>
                    </span>
                    @unless ($done)<a href="{{ $url }}" class="text-forest-600 hover:underline">Set up</a>@endunless
                </li>
            @endforeach
        </ul>
    </div>
    <div class="grid grid-cols-2 gap-4">
        @foreach ([['Active services', $counts['services'], 'agent.services.index'], ['Projects', $counts['projects'], 'agent.projects.index'], ['CV versions', $counts['cvs'], 'agent.cvs.index'], ['Signatures', $counts['signatures'], 'agent.signatures.index']] as [$label, $n, $route])
            <a href="{{ route($route) }}" class="card flex flex-col justify-center p-4 hover:border-forest-500">
                <div class="text-3xl font-semibold">{{ $n }}</div>
                <div class="text-sm text-slate-500">{{ $label }}</div>
            </a>
        @endforeach
    </div>
</div>
@endsection
