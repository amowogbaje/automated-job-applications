@extends('layouts.app')
@section('title', 'Opportunities')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Opportunities</h1>
        <p class="text-sm text-slate-500">Rejected and archived are hidden unless you filter for them or tick "show all".</p>
    </div>
    <a href="{{ route('opportunities.create') }}" class="btn-primary">Add opportunity</a>
</div>

<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4" x-data>
    <input name="q" value="{{ request('q') }}" placeholder="Search title or company" class="input sm:col-span-2 lg:col-span-4">
    <select name="status" class="input" @change="$root.submit()">
        <option value="">Any status</option>
        @foreach (config('opportunity.statuses') as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
    </select>
    <select name="priority" class="input" @change="$root.submit()">
        <option value="">Any priority</option>
        @foreach (['A', 'B', 'C'] as $p)<option value="{{ $p }}" @selected(request('priority') === $p)>Priority {{ $p }}</option>@endforeach
    </select>
    <select name="service_id" class="input" @change="$root.submit()">
        <option value="">Any service</option>
        @foreach ($services as $s)<option value="{{ $s->id }}" @selected(request('service_id') == $s->id)>{{ $s->name }}</option>@endforeach
    </select>
    <select name="channel" class="input" @change="$root.submit()">
        <option value="">Any channel</option>
        @foreach (config('opportunity.channels') as $k => $l)<option value="{{ $k }}" @selected(request('channel') === $k)>{{ $l }}</option>@endforeach
    </select>
    <select name="country" class="input" @change="$root.submit()">
        <option value="">Any country</option>
        @foreach ($countries as $c)<option value="{{ $c }}" @selected(request('country') === $c)>{{ $c }}</option>@endforeach
    </select>
    <select name="size" class="input" @change="$root.submit()">
        <option value="">Any company size</option>
        @foreach ($sizes as $z)<option value="{{ $z }}" @selected(request('size') === $z)>{{ $z }}</option>@endforeach
    </select>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="all" value="1" @checked(request()->boolean('all')) @change="$root.submit()"> Show all</label>
    <div class="flex gap-2">
        <button class="btn-primary">Filter</button>
        <a href="{{ route('opportunities.index') }}" class="btn-secondary">Reset</a>
    </div>
</form>

<div class="space-y-3">
    @forelse ($opportunities as $o)
        <a href="{{ route('opportunities.show', $o) }}" class="card block p-4 hover:border-indigo-300">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="font-medium">{{ $o->title }}</div>
                    <div class="text-sm text-slate-500">{{ $o->company->name }}@if ($o->company->country) · {{ $o->company->country }}@endif
                        @if ($o->primaryService) · {{ $o->primaryService->name }}@endif</div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <div class="flex items-center gap-2">
                        <x-priority-badge :priority="$o->priority" />
                        <x-status-badge :status="$o->status" />
                    </div>
                    @if ($o->total_score !== null)<div class="text-xs text-slate-500">Score {{ $o->total_score }}/100</div>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No opportunities match. <a href="{{ route('opportunities.create') }}" class="text-indigo-600 hover:underline">Add one</a>.</div>
    @endforelse
</div>
<div class="mt-4">{{ $opportunities->links() }}</div>
@endsection
