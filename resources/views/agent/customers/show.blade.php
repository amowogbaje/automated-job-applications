@extends('agent.layout')
@section('agent_title', $company->name)
@section('agent')
<div class="mb-6"><a href="{{ route('agent.customers.index') }}" class="text-sm text-slate-500 hover:underline">← Customers</a>
    <h1 class="text-2xl font-semibold">{{ $company->name }}</h1>
    <p class="text-sm text-slate-500">Customer since {{ $company->customer_since?->format('M j, Y') ?? '—' }}
        @foreach ($company->opportunities as $o) · <a class="text-forest-600 hover:underline" href="{{ route('agent.opportunities.show', $o) }}">{{ $o->title }}</a>@endforeach</p></div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="card p-4"><div class="text-xs text-slate-500">Paid</div>@forelse ($totals as $cur => $sum)<div class="text-xl font-semibold">{{ $cur }} {{ number_format($sum, 2) }}</div>@empty<div class="text-slate-400">—</div>@endforelse</div>
    <div class="card p-4"><div class="text-xs text-slate-500">Pending</div>@forelse ($pending as $cur => $sum)<div class="text-xl font-semibold">{{ $cur }} {{ number_format($sum, 2) }}</div>@empty<div class="text-slate-400">—</div>@endforelse</div>
    <div class="card p-4 text-sm"><div class="text-xs text-slate-500">Contacts</div>@foreach ($company->contacts as $c)<div class="truncate">{{ $c->value }}@if ($c->person_name) <span class="text-slate-500">({{ $c->person_name }})</span>@endif</div>@endforeach</div>
</div>

<div class="card mb-6 p-5">
    <h2 class="mb-3 font-medium">Record a transaction</h2>
    <form method="POST" action="{{ route('agent.transactions.store', $company) }}" class="grid gap-3 sm:grid-cols-6">
        @csrf
        <div class="sm:col-span-3"><label class="label">What for</label><input name="description" required class="input" placeholder="Website build, deposit, monthly retainer..."></div>
        <div><label class="label">Amount</label><input name="amount" type="number" step="0.01" required class="input"></div>
        <div><label class="label">Currency</label><input name="currency" value="NGN" maxlength="3" class="input uppercase"></div>
        <div><label class="label">Status</label><select name="status" class="input">@foreach (\App\Models\Transaction::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div><label class="label">Date</label><input name="occurred_on" type="date" value="{{ now()->toDateString() }}" required class="input"></div>
        <div class="sm:col-span-2"><label class="label">Reference</label><input name="reference" class="input" placeholder="Invoice or receipt no."></div>
        <div class="sm:col-span-3"><label class="label">Notes</label><input name="notes" class="input"></div>
        <div class="self-end"><button class="btn-primary">Add</button></div>
    </form>
    <p class="mt-2 text-xs text-slate-500">Use a negative amount for a refund.</p>
</div>

<div class="card divide-y divide-slate-100">
    @forelse ($transactions as $t)
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
            <div><div class="font-medium">{{ $t->description }}</div><div class="text-xs text-slate-500">{{ $t->occurred_on->format('M j, Y') }}@if ($t->reference) · {{ $t->reference }}@endif @if ($t->notes) · {{ $t->notes }}@endif</div></div>
            <div class="flex items-center gap-3">
                <span class="font-medium">{{ $t->currency }} {{ number_format($t->amount, 2) }}</span>
                <form method="POST" action="{{ route('agent.transactions.update', $t) }}">@csrf @method('PATCH')
                    <select name="status" class="input w-auto py-1 text-xs" onchange="this.form.submit()">@foreach (\App\Models\Transaction::STATUSES as $k => $l)<option value="{{ $k }}" @selected($t->status === $k)>{{ $l }}</option>@endforeach</select></form>
                <form method="POST" action="{{ route('agent.transactions.destroy', $t) }}" onsubmit="return confirm('Delete this transaction?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete</button></form>
            </div>
        </div>
    @empty
        <p class="p-6 text-center text-sm text-slate-500">No transactions yet.</p>
    @endforelse
</div>
@endsection
