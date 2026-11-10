@extends('agent.layout')
@section('agent_title', 'Leads')
@section('agent')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">Leads</h1>
        <p class="text-sm text-slate-500">Leads you found yourself and uploaded. They live in the same pipeline as Opportunities.</p>
    </div>
    <a href="{{ route('agent.leads.import') }}" class="btn-primary">Upload leads</a>
</div>

@if (session('import_messages'))
    <div class="card mb-4 p-4 text-sm text-slate-600"><ul class="list-inside list-disc">@foreach (session('import_messages') as $m)<li>{{ $m }}</li>@endforeach</ul></div>
@endif

<div class="mb-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
    @foreach (['qualified', 'contacted', 'replied', 'not_responding', 'won', 'lost'] as $k)
        <a href="{{ route('agent.leads.index', ['status' => $k]) }}" class="card p-3 text-center hover:border-forest-500">
            <div class="text-2xl font-semibold">{{ $counts[$k] ?? 0 }}</div>
            <div class="text-xs text-slate-500">{{ config('opportunity.statuses')[$k] }}</div>
        </a>
    @endforeach
</div>

<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-3">
    <input name="q" value="{{ request('q') }}" placeholder="Search company" class="input">
    <select name="batch" class="input"><option value="">Any upload</option>@foreach ($batches as $b)<option @selected(request('batch') === $b)>{{ $b }}</option>@endforeach</select>
    <div class="flex gap-2"><button class="btn-primary">Filter</button><a href="{{ route('agent.leads.index') }}" class="btn-secondary">Reset</a></div>
</form>

<div class="mb-8 space-y-3">
    @forelse ($leads as $l)
        @php $seq = $l->sequence; $c = $l->company; @endphp
        <a href="{{ route('agent.opportunities.show', $l) }}" class="card block p-4 hover:border-forest-500">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="font-medium">{{ $c->name }}</div>
                    <div class="truncate text-sm text-slate-500">
                        @foreach ($c->contacts->whereIn('type', ['email', 'phone', 'whatsapp'])->take(3) as $ct){{ $ct->value }}@if (! $loop->last) · @endif @endforeach
                        @if ($c->contacts->whereIn('type', ['email', 'phone', 'whatsapp'])->isEmpty()) No email or phone @endif
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        @if ($seq)
                            {{ $seq->mode === 'email' ? 'Email' : 'Phone' }} sequence · step {{ $seq->step }}/{{ $seq->total_steps }} · {{ $seq->state }}@if ($seq->state === 'active' && $seq->next_action_at) · next {{ $seq->next_action_at->diffForHumans() }}@endif
                        @else No follow-up started @endif
                    </div>
                </div>
                <x-agent.status-badge :status="$l->status" />
            </div>
        </a>
    @empty
        <div class="card p-8 text-center text-sm text-slate-500">No leads yet. <a href="{{ route('agent.leads.import') }}" class="text-forest-600 hover:underline">Upload some</a>.</div>
    @endforelse
    <div>{{ $leads->links() }}</div>
</div>

<form method="POST" action="{{ route('agent.leads.settings') }}" class="card space-y-4 p-5" x-data="{ auto: {{ $autoOn ? 'true' : 'false' }} }">
    @csrf @method('PUT')
    <h2 class="font-medium">Follow-up settings</h2>
    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="auto_send" value="1" x-model="auto" class="mt-1">
        <span><strong>Send follow-up emails automatically.</strong> Up to {{ count(config('leads.email_delays')) }} emails per lead ({{ implode(', ', array_map(fn ($d, $i) => $i === 0 ? 'now' : "+{$d}d", config('leads.email_delays'), array_keys(config('leads.email_delays')))) }}), only to emails you uploaded, only {{ config('leads.send_hours')[0] }}:00–{{ config('leads.send_hours')[1] }}:00. Stops on a reply or opt-out. When this is off, you are notified instead and nothing is sent.</span></label>
    <div class="grid gap-3 sm:grid-cols-3">
        <div><label class="label">Max automatic emails per day</label><input name="daily_cap" type="number" min="1" max="100" value="{{ $dailyCap }}" class="input"></div>
        <div><label class="label">Replies go to</label><input name="reply_to" type="email" value="{{ $replyTo }}" placeholder="your own email (default)" class="input"></div>
        <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" name="email_owner" value="1" @checked($emailOwner)> Also email me when I have something to do</label>
    </div>
    <details class="text-sm"><summary class="cursor-pointer font-medium">Email wording</summary>
        <p class="my-2 text-xs text-slate-500">Placeholders: {name} {company} {service} {sender} {documents}. A signature and an opt-out link are added automatically.</p>
        @foreach ($templates as $n => $t)
            <div class="mb-3 space-y-1 rounded-md border border-slate-200 p-3">
                <div class="text-xs font-medium text-slate-500">Email {{ $n }}</div>
                <input name="templates[{{ $n }}][subject]" value="{{ $t['subject'] }}" class="input">
                <textarea name="templates[{{ $n }}][body]" rows="5" class="input">{{ $t['body'] }}</textarea>
            </div>
        @endforeach
    </details>
    <div class="space-y-3 border-t border-slate-100 pt-4">
        <h3 class="text-sm font-medium">Send now button</h3>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="send_now_enabled" value="1" @checked($sendNowEnabled) class="mt-1"><span>Enable the <strong>Send now</strong> button on leads and drafts. When off, the button shows but does not send.</span></label>
        <div><label class="label">Mailbox owner (sends through the app's MAIL_* account)</label>
            <input name="smtp_user_email" type="email" value="{{ $smtpUserEmail }}" class="input">
            <p class="mt-1 text-xs text-slate-500">Only this signed-in account can Send now through the app's mail account. Anyone else must connect their own Google account.</p></div>
    </div>
    <button class="btn-primary">Save settings</button>
</form>

<div class="card mt-4 space-y-2 p-5 text-sm">
    <h2 class="font-medium">Your Google account</h2>
    @error('google')<div class="rounded-lg bg-red-100 p-2 text-red-700">{{ $message }}</div>@enderror
    @if ($google)
        <p>Connected as <strong>{{ $google->email }}</strong>. Send now uses this mailbox for you.</p>
        <form method="POST" action="{{ route('agent.google.disconnect') }}">@csrf<button class="btn-secondary">Disconnect</button></form>
    @elseif ($googleConfigured)
        <p class="text-slate-600">Connect Google so the app can send email from your own Gmail when you press Send now. It can only send; it cannot read your mail.</p>
        <a href="{{ route('agent.google.connect') }}" class="btn-primary">Connect Google</a>
    @else
        <p class="text-slate-600">Google sign-in is not set up yet. Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to .env (steps in INTEGRATION.md).</p>
    @endif
</div>
@endsection
