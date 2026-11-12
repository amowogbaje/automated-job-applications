@extends('agent.layout')
@section('agent_title', 'Alerts')
@section('agent')
<div class="mb-4 flex items-center justify-between gap-3"><h1 class="text-2xl font-semibold">Alerts</h1>
    <form method="POST" action="{{ route('agent.notifications.read-all') }}">@csrf<button class="btn-secondary">Mark all read</button></form></div>

@if ($errors->any())<div class="mb-4 rounded-lg bg-red-100 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

{{-- Due now --}}
@if ($due->isNotEmpty())
<h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Send now</h2>
<div class="mb-6 space-y-3">
    @foreach ($due as $r)
        <div class="card border-l-4 border-[#25d366] p-4">
            <div class="font-medium">Message {{ $r->opportunity->company->name }}</div>
            <p class="mt-1 line-clamp-3 whitespace-pre-wrap text-sm text-slate-600">{{ $r->message }}</p>
            <div class="btn-row mt-3 flex gap-2">
                <a href="{{ $r->waUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg bg-[#25d366] px-4 py-2.5 text-sm font-semibold text-white">Open WhatsApp</a>
                <a href="{{ route('agent.whatsapp.show', $r) }}" class="btn-secondary">Edit / log</a>
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- Coming up --}}
@if ($upcoming->isNotEmpty() || $emails->isNotEmpty())
<h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Coming up</h2>
<div class="card mb-6 divide-y divide-slate-100">
    @foreach ($upcoming as $r)
        <div class="flex items-center justify-between gap-3 p-4 text-sm">
            <div class="min-w-0"><div class="font-medium">WhatsApp · {{ $r->opportunity->company->name }}</div>
                <div class="text-xs text-slate-500"><time data-iso="{{ $r->remind_at->toIso8601String() }}">{{ $r->remind_at->format('M j, H:i') }}</time></div></div>
            <div class="flex shrink-0 gap-3 text-xs"><a class="text-forest-600" href="{{ route('agent.whatsapp.show', $r) }}">Open</a>
                <form method="POST" action="{{ route('agent.whatsapp.dismiss', $r) }}">@csrf<button class="text-red-600">Cancel</button></form></div>
        </div>
    @endforeach
    @foreach ($emails as $e)
        <div class="flex items-center justify-between gap-3 p-4 text-sm">
            <div class="min-w-0"><div class="truncate font-medium">Email · {{ $e->opportunity->company->name }}</div>
                <div class="truncate text-xs text-slate-500">{{ $e->subject }} · to {{ $e->contact?->value }}</div>
                <div class="text-xs {{ $e->status === 'pending' ? 'text-slate-500' : 'text-red-600' }}">@if ($e->status === 'pending')<time data-iso="{{ $e->send_at->toIso8601String() }}">{{ $e->send_at->format('M j, H:i') }}</time>@else{{ ucfirst($e->status) }}: {{ $e->error }}@endif</div></div>
            @if ($e->status === 'pending')
                <div class="flex shrink-0 gap-3 text-xs">
                    <form method="POST" action="{{ route('agent.scheduled.send', $e) }}" onsubmit="return confirm('Send this email now?')">@csrf<button class="text-forest-600">Send now</button></form>
                    <form method="POST" action="{{ route('agent.scheduled.cancel', $e) }}">@csrf<button class="text-red-600">Cancel</button></form>
                </div>
            @endif
        </div>
    @endforeach
</div>
@endif

<div class="card mb-6 p-5 text-sm" x-data="pushPanel()" x-init="init()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="font-medium">Push notifications on this device</div>
            <p class="text-xs text-slate-500" x-text="message"></p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn-primary" x-show="supported && !on" @click="enable()">Turn on</button>
            <button type="button" class="btn-secondary" x-show="on" @click="test()">Send test</button>
            <button type="button" class="btn-secondary" x-show="on" @click="disable()">Turn off</button>
        </div>
    </div>
    <p class="mt-2 text-xs text-slate-400">Do this once on each phone or computer you want alerted. On iPhone: add this site to your Home Screen first (Share → Add to Home Screen), then open it from there.</p>
</div>
<script>
function pushPanel() {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const post = (url, body) => fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body || {}) });
    const toKey = (b64) => Uint8Array.from(atob(b64.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(b64.length / 4) * 4, '=')), (c) => c.charCodeAt(0));
    return {
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        on: false, message: '',
        async init() {
            if (!this.supported) { this.message = 'This browser does not support push notifications (on iPhone, open the site from the Home Screen icon).'; return; }
            const reg = await navigator.serviceWorker.register('/sw.js');
            const sub = await reg.pushManager.getSubscription();
            this.on = !!sub && Notification.permission === 'granted';
            this.message = this.on ? 'On. You will be alerted when a lead needs you.' : 'Off on this device.';
        },
        async enable() {
            try {
                if ((await Notification.requestPermission()) !== 'granted') { this.message = 'Permission was blocked. Allow notifications for this site in your browser settings.'; return; }
                const reg = await navigator.serviceWorker.ready;
                const { key } = await (await fetch('{{ route('agent.push.key') }}', { credentials: 'same-origin' })).json();
                const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(key) });
                await post('{{ route('agent.push.subscribe') }}', { endpoint: sub.endpoint });
                this.on = true; this.message = 'On. You will be alerted when a lead needs you.';
            } catch (e) { this.message = 'Could not turn on: ' + e.message; }
        },
        async disable() {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (sub) { await post('{{ route('agent.push.unsubscribe') }}', { endpoint: sub.endpoint }); await sub.unsubscribe(); }
            this.on = false; this.message = 'Off on this device.';
        },
        async test() {
            const r = await (await post('{{ route('agent.push.test') }}')).json();
            this.message = r.sent ? 'Test sent. It should appear in a moment.' : 'The push service did not accept it (sent ' + (r.sent || 0) + ', failed ' + (r.failed || 0) + '). Try turning it off and on again.';
        },
    };
}
</script>
<div class="card mb-6 p-5 text-sm" x-data="{ on: (() => { try { return localStorage.getItem('alertSound') !== 'off' } catch (e) { return true } })(), set(v) { this.on = v; try { localStorage.setItem('alertSound', v ? 'on' : 'off') } catch (e) {} } }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><div class="font-medium">Sound alert</div>
            <p class="text-xs text-slate-500">While the app is open, a chime plays and a voice says "It's time to send your WhatsApp message". When the app is closed, your phone's notification sound is used.</p></div>
        <div class="flex gap-2">
            <button type="button" class="btn-secondary" x-on:click="window.agentAlertTest && window.agentAlertTest()">Test sound</button>
            <button type="button" class="btn-secondary" x-on:click="set(!on)" x-text="on ? 'Sound on' : 'Sound off'"></button>
        </div>
    </div>
</div>

<h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Activity</h2>
<div class="space-y-3">
    @forelse ($items as $n)
        <div class="card p-4 {{ $n->read_at ? 'opacity-60' : 'border-amber-300' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium">{{ $n->title }}</div>
                    @if ($n->body)<pre class="mt-1 whitespace-pre-wrap font-sans text-sm text-slate-600">{{ $n->body }}</pre>@endif
                    <div class="mt-1 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1 text-sm">
                    @if ($n->url)<a class="font-medium text-forest-600 hover:underline" href="{{ $n->url }}">Open</a>@endif
                    @if ($n->opportunity)<a class="text-forest-600 hover:underline" href="{{ route('agent.opportunities.show', $n->opportunity) }}">Lead</a>@endif
                    @unless ($n->read_at)<form method="POST" action="{{ route('agent.notifications.read', $n) }}">@csrf<button class="text-xs text-slate-500 hover:underline">Mark read</button></form>@endunless
                </div>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">Nothing yet. You'll be told here when a lead needs a phone/WhatsApp message or an email is due.</div>
    @endforelse
</div>
@endsection
