@extends('agent.layout')
@section('agent_title', 'Notifications')
@section('agent')
<div class="mb-6 flex items-center justify-between"><h1 class="text-2xl font-semibold">Notifications</h1>
    <form method="POST" action="{{ route('agent.notifications.read-all') }}">@csrf<button class="btn-secondary">Mark all read</button></form></div>

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
                    @if ($n->opportunity)<a class="text-forest-600 hover:underline" href="{{ route('agent.opportunities.show', $n->opportunity) }}">Open lead</a>@endif
                    @unless ($n->read_at)<form method="POST" action="{{ route('agent.notifications.read', $n) }}">@csrf<button class="text-xs text-slate-500 hover:underline">Mark read</button></form>@endunless
                </div>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">Nothing yet. You'll be told here when a lead needs a phone/WhatsApp message or an email is due.</div>
    @endforelse
</div>
@endsection
