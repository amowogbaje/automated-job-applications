@extends('agent.layout')
@section('agent_title', 'Send email')
@section('agent')
<div class="mb-6"><a href="{{ route('agent.opportunities.show', $opportunity) }}" class="text-sm text-slate-500 hover:underline">← {{ $company->name }}</a>
    <h1 class="text-2xl font-semibold">Send email now</h1></div>

@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-100 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="card mb-4 p-4 text-sm">
    @if ($status['can'])
        <span class="badge bg-green-100 text-green-700">Ready</span>
        Sending from <strong>{{ $status['from'] }}</strong> {{ $status['mode'] === 'smtp' ? "(the app's mail account)" : '(your Gmail)' }}.
    @else
        <span class="badge bg-amber-100 text-amber-700">Not available</span> {{ $status['reason'] }}
        @if ($status['mode'] === 'connect')
            <a href="{{ route('agent.google.connect') }}" class="btn-primary mt-3">Connect Google</a>
        @endif
    @endif
</div>

@if ($contacts->isEmpty())
    <div class="card p-6 text-center text-sm text-slate-500">This company has no active email contact. Add one on its page first.</div>
@else
<form method="POST" action="{{ route('agent.send.store', $opportunity) }}" class="card space-y-4 p-5" data-when x-data="{ later: {{ old('send_at') ? 'true' : 'false' }} }">
    @csrf
    @if ($draft)<input type="hidden" name="draft_id" value="{{ $draft->id }}">@endif
    <div><label class="label">To</label>
        <select name="contact_id" class="input">@foreach ($contacts as $c)<option value="{{ $c->id }}" @selected(old('contact_id', $selected?->id) == $c->id)>{{ $c->value }}@if ($c->person_name) ({{ $c->person_name }})@endif</option>@endforeach</select></div>
    <div><label class="label">Subject</label><input name="subject" value="{{ old('subject', $subject) }}" required maxlength="200" class="input"></div>
    <div><label class="label">Message</label><textarea name="body" rows="12" required class="input">{{ old('body', $body) }}</textarea>
        <p class="mt-1 text-xs text-slate-500">An opt-out link is added at the bottom automatically. Read it through; this goes out as soon as you confirm.</p></div>
    @if ($docs->isNotEmpty())
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="attach_docs" value="1" class="mt-1" @checked(old('attach_docs'))><span>Include documents ({{ $docs->pluck('title')->implode(', ') }}): attached and linked.</span></label>
    @endif
    <input type="hidden" name="tz_offset" value="0">
    <div x-show="later" x-cloak class="rounded-xl bg-slate-100 p-3">
        <label class="label">Send at (your time)</label>
        <input type="datetime-local" name="send_at" value="{{ old('send_at') }}" class="input">
        <div class="mt-2 flex flex-wrap gap-2 text-xs">
            <button type="button" data-preset="1h" class="rounded-full bg-white px-3 py-1.5">In 1 hour</button>
            <button type="button" data-preset="tom" class="rounded-full bg-white px-3 py-1.5">Tomorrow 9am</button>
            <button type="button" data-preset="mon" class="rounded-full bg-white px-3 py-1.5">Monday 9am</button>
        </div>
        <p class="mt-2 text-xs text-slate-500">It is sent automatically at that time, from the same mailbox as Send now. You can cancel it under Alerts. It is skipped if the lead replies first.</p>
    </div>
    <div class="btn-row flex flex-wrap gap-2">
        <button x-show="!later" name="action" value="send" class="btn-primary" @disabled(! $status['can']) onclick="return confirm('Send this email now?')">Send now</button>
        <button x-show="!later" type="button" class="btn-secondary" x-on:click="later = true" @disabled(! $status['can'])>Send later…</button>
        <button x-show="later" x-cloak name="action" value="schedule" class="btn-primary" @disabled(! $status['can'])>Schedule it</button>
        <button x-show="later" x-cloak type="button" class="btn-secondary" x-on:click="later = false">Back</button>
    </div>
</form>
@endif
@endsection
