@php
    $seq = $opportunity->sequence;
    $docs = \App\Models\SharedDocument::latest()->get();
    $phone = $company->contacts->whereIn('type', ['phone', 'whatsapp'])->where('status', 'active')->first();
    $hasEmail = $company->contacts->where('type', 'email')->where('status', 'active')->isNotEmpty();
    $shareText = 'Hi'.($phone?->person_name ? ' '.explode(' ', $phone->person_name)[0] : '').", this is ".\App\Services\Agent\Leads\LeadMailer::ownerName().'. '.($docs->isEmpty() ? '' : "Here are the details:\n".$docs->map(fn ($d) => $d->title.': '.$d->publicUrl())->implode("\n"));
@endphp
<div class="card space-y-3 p-5" x-data="{ share: false }">
    <h2 class="font-medium">Lead follow-up</h2>
    @if ($seq)
        <p class="text-sm text-slate-600">
            {{ $seq->mode === 'email' ? 'Email sequence' : 'Phone / WhatsApp' }} · step {{ $seq->step }} of {{ $seq->total_steps }} ·
            <strong>{{ $seq->state }}</strong>@if ($seq->state === 'active' && $seq->next_action_at) · next check {{ $seq->next_action_at->diffForHumans() }}@endif
            @if ($seq->stop_reason)<br><span class="text-xs text-slate-500">{{ $seq->stop_reason }}</span>@endif
        </p>
        @if ($seq->mode === 'email' && ! \App\Services\Agent\Leads\LeadSequenceRunner::autoOn())
            <p class="text-xs text-amber-700">Automatic sending is off (Leads → Follow-up settings). You'll be notified when an email is due.</p>
        @endif
        <div class="flex flex-wrap gap-2">
            @if (in_array($seq->state, ['active', 'paused']))
                <form method="POST" action="{{ route('agent.leads.sent', $opportunity) }}">@csrf<button class="btn-secondary">I sent the {{ $seq->mode === 'email' ? 'email' : 'message' }}</button></form>
                <form method="POST" action="{{ route('agent.leads.replied', $opportunity) }}">@csrf<button class="btn-secondary">They replied</button></form>
                @if ($seq->state === 'active')<form method="POST" action="{{ route('agent.leads.pause', $opportunity) }}">@csrf<button class="btn-secondary">Pause</button></form>
                @else<form method="POST" action="{{ route('agent.leads.resume', $opportunity) }}">@csrf<button class="btn-secondary">Resume</button></form>@endif
                <form method="POST" action="{{ route('agent.leads.stop', $opportunity) }}">@csrf<button class="btn-danger">Stop</button></form>
            @else
                <form method="POST" action="{{ route('agent.leads.sequence.start', $opportunity) }}">@csrf<button class="btn-secondary">Restart follow-up</button></form>
            @endif
        </div>
    @elseif ($hasEmail || $phone)
        <form method="POST" action="{{ route('agent.leads.sequence.start', $opportunity) }}">@csrf<button class="btn-primary">Start follow-up</button></form>
    @else
        <p class="text-sm text-slate-500">Add an email or phone contact to follow this lead up.</p>
    @endif

    @if ($hasEmail)
        <a href="{{ route('agent.send.compose', $opportunity) }}" class="btn-secondary">Send email now</a>
    @endif

    @if ($phone)
        <div class="flex flex-wrap gap-2 text-sm">
            <a class="text-forest-600 hover:underline" target="_blank" rel="noopener" href="{{ \App\Services\Agent\Leads\Phone::waLink($phone->value) }}">Open WhatsApp</a>
            <a class="text-forest-600 hover:underline" href="tel:{{ $phone->value }}">Call {{ $phone->value }}</a>
        </div>
    @endif

    <div>
        <button type="button" @click="share = !share" class="text-sm text-forest-600 hover:underline">Share documents</button>
        <div x-show="share" x-cloak class="mt-2 space-y-1">
            @if ($docs->isEmpty())<p class="text-xs text-slate-500">Upload a rate card or services sheet under Documents first.</p>
            @else
                <textarea readonly rows="5" class="input text-xs" onclick="this.select()">{{ $shareText }}</textarea>
                <p class="text-xs text-slate-500">Copy into WhatsApp or an email. The links open the file without a login.</p>
            @endif
        </div>
    </div>

    @if ($opportunity->status !== 'won')
        <form method="POST" action="{{ route('agent.leads.convert', $opportunity) }}" onsubmit="return confirm('Convert this lead to a customer?')">@csrf<button class="btn-primary">Convert to customer</button></form>
    @elseif ($company->is_customer)
        <a href="{{ route('agent.customers.show', $company) }}" class="btn-secondary">Open customer & transactions</a>
    @endif
</div>
