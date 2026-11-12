@extends('agent.layout')
@section('agent_title', 'WhatsApp')
@section('agent')
@php $r = $reminder; $c = $r->opportunity->company; @endphp
<div class="mb-4"><a href="{{ route('agent.opportunities.show', $r->opportunity) }}" class="text-sm text-slate-500 hover:underline">← {{ $c->name }}</a>
    <h1 class="text-2xl font-semibold">Message {{ $c->name }}</h1>
    <p class="text-sm text-slate-500">{{ $r->phone }} · @if ($r->status === 'pending')reminder set for <time data-iso="{{ $r->remind_at->toIso8601String() }}">{{ $r->remind_at->format('M j, H:i') }}</time>@elseif ($r->status === 'due')due now@else{{ $r->status }}@endif</p></div>

<div class="card space-y-4 p-5" x-data="{ msg: @js($r->message), base: @js('https://wa.me/'.ltrim($r->phone, '+')), save() { fetch('{{ route('agent.whatsapp.update', $r) }}', { method: 'PATCH', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ message: this.msg }) }); } }">
    <div><label class="label">Message (edit it if you like)</label>
        <textarea x-model="msg" @blur="save()" rows="6" class="input"></textarea></div>
    <a :href="base + '?text=' + encodeURIComponent(msg)" target="_blank" rel="noopener" x-on:click="save()"
       class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#25d366] px-4 py-4 text-base font-semibold text-white shadow active:scale-[.98]">Open WhatsApp with this message</a>
    <p class="text-xs text-slate-500">WhatsApp opens with the number and message ready. You just press Send there.</p>
    <a href="tel:{{ $r->phone }}" class="btn-secondary w-full">Call instead</a>
</div>

@if (in_array($r->status, ['pending', 'due']))
<div class="mt-4 grid gap-2 sm:grid-cols-4">
    <form method="POST" action="{{ route('agent.whatsapp.sent', $r) }}">@csrf<button class="btn-primary w-full">I sent it</button></form>
    <form method="POST" action="{{ route('agent.whatsapp.snooze', $r) }}">@csrf<input type="hidden" name="minutes" value="60"><button class="btn-secondary w-full">Snooze 1 hour</button></form>
    <form method="POST" action="{{ route('agent.whatsapp.snooze', $r) }}">@csrf<input type="hidden" name="minutes" value="1440"><button class="btn-secondary w-full">Tomorrow</button></form>
    <form method="POST" action="{{ route('agent.whatsapp.dismiss', $r) }}">@csrf<button class="btn-secondary w-full">Dismiss</button></form>
</div>
@endif
@endsection
