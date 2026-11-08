@extends('agent.layout')
@section('agent_title', 'Customers')
@section('agent')
<div class="mb-6"><h1 class="text-2xl font-semibold">Customers</h1><p class="text-sm text-slate-500">Leads you converted. Open one to record transactions.</p></div>
<div class="space-y-3">
    @forelse ($customers as $c)
        <a href="{{ route('agent.customers.show', $c) }}" class="card flex items-center justify-between gap-4 p-4 hover:border-forest-500">
            <div><div class="font-medium">{{ $c->name }}</div><div class="text-xs text-slate-500">Customer since {{ $c->customer_since?->format('M j, Y') ?? '—' }} · {{ $c->transactions_count }} transaction(s)</div></div>
            <div class="text-right text-sm">@forelse ($paid[$c->id] ?? [] as $p)<div>{{ $p->currency }} {{ number_format($p->total, 2) }} <span class="text-xs text-slate-500">paid</span></div>@empty<span class="text-xs text-slate-400">nothing paid yet</span>@endforelse</div>
        </a>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No customers yet. Open a lead and choose "Convert to customer".</div>
    @endforelse
</div>
@endsection
