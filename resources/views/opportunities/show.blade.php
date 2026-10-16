@extends('layouts.app')
@section('title', $opportunity->title)
@section('content')
@php
    $currencyLine = null;
    if ($opportunity->budget_min || $opportunity->budget_max) {
        $currencyLine = trim(($opportunity->budget_currency ?? '').' '.number_format((int) $opportunity->budget_min).($opportunity->budget_max ? ' – '.number_format((int) $opportunity->budget_max) : ''));
    }
@endphp

{{-- Header --}}
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <a href="{{ route('opportunities.index') }}" class="text-xs text-indigo-600 hover:underline">← Opportunities</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $opportunity->title }}</h1>
        <p class="text-sm text-slate-500">
            {{ $company->name }}
            @if ($company->website_url) · <a class="text-indigo-600 hover:underline" target="_blank" rel="noopener" href="{{ $company->website_url }}">{{ $company->domain }}</a>@endif
            @if ($company->country) · {{ $company->city ? $company->city.', ' : '' }}{{ $company->country }}@endif
            @if ($company->industry) · {{ $company->industry }}@endif
            @if ($company->size_band) · {{ $company->size_band }} people@endif
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-status-badge :status="$opportunity->status" />
            <x-priority-badge :priority="$opportunity->priority" />
            @if ($opportunity->total_score !== null)<span class="badge bg-slate-100 text-slate-700">Score {{ $opportunity->total_score }}/100</span>@endif
            @if ($opportunity->confidence)<span class="badge bg-slate-100 text-slate-600">Confidence: {{ $opportunity->confidence }}</span>@endif
            @if ($company->do_not_contact)<span class="badge bg-red-100 text-red-700">Do not contact</span>@endif
        </div>
    </div>
    <div class="flex items-center gap-1">
        <a href="{{ route('opportunities.edit', $opportunity) }}" class="btn-secondary">Edit</a>
        <form method="POST" action="{{ route('opportunities.destroy', $opportunity) }}" onsubmit="return confirm('Delete this opportunity and its evidence?')">
            @csrf @method('DELETE')<button class="btn-danger">Delete</button>
        </form>
    </div>
</div>

{{-- Pipeline status --}}
<form method="POST" action="{{ route('opportunities.status', $opportunity) }}" class="card mb-6 flex flex-wrap items-center gap-3 p-3"
      x-data="{ status: '{{ $opportunity->status }}' }">
    @csrf @method('PATCH')
    <label class="text-sm font-medium">Move to</label>
    <select name="status" x-model="status" class="input w-auto">
        @foreach (config('opportunity.statuses') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
    </select>
    <input name="rejection_reason" x-show="status === 'rejected'" x-cloak placeholder="Why is it rejected?" value="{{ $opportunity->rejection_reason }}" class="input min-w-[14rem] flex-1">
    <button class="btn-primary">Update</button>
    @if ($opportunity->status === 'rejected' && $opportunity->rejection_reason)
        <span class="text-xs text-slate-500">Rejected: {{ $opportunity->rejection_reason }}</span>
    @endif
</form>

<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">

    {{-- Research agent --}}
    <script>
    function researchPanel(initial) {
        return {
            run: initial, timer: null,
            active() { return this.run && ['pending', 'running'].includes(this.run.status); },
            init() { if (this.active()) this.poll(); },
            poll() {
                this.timer = setInterval(async () => {
                    try {
                        const r = await fetch(this.run.url, { headers: { Accept: 'application/json' } });
                        if (!r.ok) return;
                        this.run = await r.json();
                        if (!this.active()) { clearInterval(this.timer); location.reload(); }
                    } catch (e) {}
                }, 4000);
            },
        };
    }
    </script>
    <div class="card p-5" x-data="researchPanel(@js($runPayload))">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-medium">Research agent</h2>
            <form method="POST" action="{{ route('research.start', $opportunity) }}">
                @csrf
                <button class="btn-primary" :disabled="active()" :class="active() ? 'opacity-50' : ''" @disabled(! $aiReady)>
                    <span x-text="active() ? 'Researching...' : 'Run research'"></span>
                </button>
            </form>
        </div>
        @unless ($aiReady)
            <p class="mb-2 text-sm text-amber-700">No AI provider is ready. <a href="{{ route('providers.index') }}" class="underline">Add an API key on the Providers page.</a></p>
        @endunless
        <p class="text-xs text-slate-500">Reads the company's public pages, searches the web, records evidence with sources, and suggests scores. It never sends anything, and it only fills fields you have left blank.</p>

        <template x-if="run">
            <div class="mt-4">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge" :class="{'bg-green-100 text-green-700': run.status === 'completed', 'bg-red-100 text-red-700': run.status === 'failed', 'bg-sky-100 text-sky-700': active()}" x-text="run.status"></span>
                    <span class="text-xs text-slate-500" x-show="run.model" x-text="'via ' + run.model"></span>
                </div>
                <p class="mt-2 text-sm text-red-700" x-show="run.error" x-text="run.error"></p>
                <p class="mt-2 text-sm text-slate-700" x-show="run.summary" x-text="run.summary"></p>
                <ol class="mt-3 space-y-1.5 text-xs">
                    <template x-for="s in run.steps" :key="s.n">
                        <li class="flex gap-2">
                            <span :class="s.ok ? 'text-green-600' : 'text-red-600'" x-text="s.ok ? '✓' : '✗'"></span>
                            <span><span class="font-medium" x-text="s.tool"></span>
                                <span class="text-slate-400" x-show="s.ms" x-text="' · ' + s.ms + ' ms'"></span>
                                <span class="block text-slate-500" x-text="s.text"></span></span>
                        </li>
                    </template>
                </ol>
            </div>
        </template>
    </div>

    {{-- Overview --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-medium">Overview</h2>
        @if ($opportunity->summary)<p class="text-sm">{{ $opportunity->summary }}</p>@endif
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs uppercase text-slate-500">Type</dt><dd>{{ config('opportunity.types')[$opportunity->type] ?? $opportunity->type }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Engagement</dt><dd>{{ config('opportunity.engagements')[$opportunity->engagement_type] ?? '—' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Budget</dt><dd>{{ $currencyLine ?: '—' }}@if ($currencyLine) <span class="text-xs text-slate-500">({{ $opportunity->budget_is_verified ? 'from source' : 'my estimate' }})</span>@endif</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Dates</dt><dd>
                @if ($opportunity->posted_at)Posted {{ $opportunity->posted_at->format('M j, Y') }}@endif
                @if ($opportunity->expires_at) · closes {{ $opportunity->expires_at->format('M j, Y') }}@endif
                @if (! $opportunity->posted_at && ! $opportunity->expires_at)—@endif
            </dd></div>
            <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Source</dt><dd>
                @if ($opportunity->source_url)<a class="break-all text-indigo-600 hover:underline" target="_blank" rel="noopener" href="{{ $opportunity->source_url }}">{{ $opportunity->source_url }}</a>@else—@endif
            </dd></div>
            <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Services</dt><dd class="mt-1 flex flex-wrap gap-1">
                @forelse ($opportunity->services as $s)
                    <span class="badge {{ $s->pivot->is_primary ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $s->name }}{{ $s->pivot->is_primary ? ' · primary' : '' }}</span>
                @empty —@endforelse
            </dd></div>
        </dl>
        @if ($opportunity->business_need)
            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                <div class="mb-1 text-xs font-medium uppercase text-slate-500">Business need (evidenced)</div>{{ $opportunity->business_need }}
            </div>
        @endif
        @if ($opportunity->inferred_opportunity)
            <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm">
                <div class="mb-1 text-xs font-medium uppercase text-amber-700">Inferred opportunity (not verified)</div>{{ $opportunity->inferred_opportunity }}
            </div>
        @endif
    </div>

    {{-- Evidence --}}
    <div class="card p-5" x-data="{ open: false }">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-medium">Evidence trail <span class="text-sm font-normal text-slate-500">({{ $opportunity->evidence->count() }})</span></h2>
            <button type="button" @click="open = !open" class="btn-secondary" x-text="open ? 'Close' : 'Add evidence'"></button>
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('evidence.store', $opportunity) }}" class="mb-4 space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4">
            @csrf
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">Kind</label>
                    <select name="kind" class="input">@foreach (config('opportunity.evidence_kinds') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Source URL (required)</label>
                    <input name="source_url" type="url" required placeholder="https://" class="input">
                </div>
            </div>
            <div><label class="label">Source title</label><input name="source_title" class="input"></div>
            <div><label class="label">What I found</label><textarea name="finding" rows="2" required class="input"></textarea></div>
            <div><label class="label">What it actually establishes (fact)</label><textarea name="establishes" rows="2" class="input"></textarea></div>
            <div><label class="label">What I infer from it (not fact)</label><textarea name="inference" rows="2" class="input"></textarea></div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">Confidence</label>
                    <select name="confidence" class="input"><option value="low">Low</option><option value="moderate" selected>Moderate</option><option value="high">High</option></select>
                </div>
                <div class="sm:col-span-2"><label class="label">Short excerpt</label><input name="excerpt" class="input"></div>
            </div>
            <button class="btn-primary">Save evidence</button>
        </form>

        <div class="space-y-3">
            @forelse ($opportunity->evidence as $e)
                <div class="rounded-md border border-slate-200 p-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge bg-slate-100 text-slate-600">{{ config('opportunity.evidence_kinds')[$e->kind] ?? $e->kind }}</span>
                            <span class="badge {{ ['high' => 'bg-green-100 text-green-700', 'moderate' => 'bg-amber-100 text-amber-700', 'low' => 'bg-red-50 text-red-600'][$e->confidence] ?? '' }}">{{ $e->confidence }}</span>
                            @if ($e->is_stale)<span class="badge bg-red-100 text-red-700">stale</span>@endif
                        </div>
                        <form method="POST" action="{{ route('evidence.destroy', $e) }}" onsubmit="return confirm('Remove this evidence?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form>
                    </div>
                    <p class="mt-2">{{ $e->finding }}</p>
                    @if ($e->establishes)<p class="mt-1 text-slate-600"><span class="font-medium">Establishes:</span> {{ $e->establishes }}</p>@endif
                    @if ($e->inference)<p class="mt-1 text-amber-700"><span class="font-medium">Inferred:</span> {{ $e->inference }}</p>@endif
                    @if ($e->excerpt)<p class="mt-1 border-l-2 border-slate-300 pl-2 text-slate-500">{{ $e->excerpt }}</p>@endif
                    <p class="mt-2 text-xs text-slate-500">
                        <a class="text-indigo-600 hover:underline" target="_blank" rel="noopener" href="{{ $e->source_url }}">{{ $e->source_title ?: parse_url($e->source_url, PHP_URL_HOST) }}</a>
                        · retrieved {{ $e->retrieved_at->format('M j, Y') }}
                    </p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No evidence yet. Record what you found, where, and what it does and doesn't prove, before contacting anyone.</p>
            @endforelse
        </div>
    </div>

    {{-- Contacts --}}
    <div class="card p-5" x-data="{ open: false, type: 'email' }">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-medium">Contacts <span class="text-sm font-normal text-slate-500">({{ $company->contacts->count() }})</span></h2>
            <button type="button" @click="open = !open" class="btn-secondary" x-text="open ? 'Close' : 'Add contact'"></button>
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('contacts.store', $opportunity) }}" class="mb-4 space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Type</label>
                    <select name="type" x-model="type" class="input">@foreach (config('opportunity.contact_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="label" x-text="['email'].includes(type) ? 'Email address' : (['whatsapp','phone'].includes(type) ? 'Number (with country code)' : 'URL / details')"></label>
                    <input name="value" required class="input">
                </div>
                <div><label class="label">Label</label><input name="label" placeholder="e.g. Sales enquiries" class="input"></div>
                <div><label class="label">Person (if named publicly)</label><input name="person_name" class="input"></div>
                <div><label class="label">Role</label><input name="person_role" class="input"></div>
                <div><label class="label">Public page where I found it (required)</label><input name="source_url" type="url" required placeholder="https://" class="input"></div>
            </div>
            <div><label class="label">Restrictions / contact instructions</label><textarea name="restrictions" rows="2" class="input" placeholder="e.g. 'No agencies', 'apply via portal only'"></textarea></div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_public_business_channel" value="1"> Publicly published as a business channel</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_preferred" value="1"> Preferred channel</label>
            </div>
            <p class="text-xs text-slate-500">Only save contacts the company publishes. Don't guess emails, and don't treat a personal phone number as WhatsApp.</p>
            <button class="btn-primary">Save contact</button>
        </form>

        <div class="space-y-3">
            @forelse ($company->contacts as $c)
                <div class="rounded-md border border-slate-200 p-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge bg-slate-100 text-slate-600">{{ config('opportunity.contact_types')[$c->type] ?? $c->type }}</span>
                                @if ($c->is_preferred)<span class="badge bg-green-100 text-green-700">preferred</span>@endif
                                @if ($c->is_public_business_channel)<span class="badge bg-sky-100 text-sky-700">public business channel</span>@else<span class="badge bg-amber-100 text-amber-700">not confirmed as business channel</span>@endif
                                @if ($c->status !== 'active')<span class="badge bg-red-100 text-red-700">{{ $c->status }}</span>@endif
                            </div>
                            <div class="mt-1 flex items-center gap-2" x-data="{ copied: false }">
                                <span class="break-all font-medium">{{ $c->value }}</span>
                                <button type="button" class="text-xs text-indigo-600 hover:underline"
                                        @click="navigator.clipboard.writeText(@js($c->value)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button>
                            </div>
                            @if ($c->label || $c->person_name)<div class="text-slate-500">{{ collect([$c->label, $c->person_name, $c->person_role])->filter()->implode(' · ') }}</div>@endif
                            @if ($c->restrictions)<div class="mt-1 text-amber-700">Note: {{ $c->restrictions }}</div>@endif
                            <div class="mt-1 text-xs text-slate-500">
                                Found at <a class="text-indigo-600 hover:underline" target="_blank" rel="noopener" href="{{ $c->source_url }}">{{ parse_url($c->source_url, PHP_URL_HOST) }}</a>
                                @if ($c->verified_at) · verified {{ $c->verified_at->format('M j, Y') }}@endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('contacts.destroy', $c) }}" onsubmit="return confirm('Remove this contact?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No contact route saved yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Outreach log --}}
    <div class="card p-5" x-data="{ open: false }">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-medium">Outreach log</h2>
            <button type="button" @click="open = !open" class="btn-secondary" x-text="open ? 'Close' : 'Log activity'"></button>
        </div>
        <p class="mb-3 text-xs text-slate-500">You send messages yourself. Log what happened here and the pipeline status follows.</p>
        <form x-show="open" x-cloak method="POST" action="{{ route('events.store', $opportunity) }}" class="mb-4 space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4">
            @csrf
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label class="label">What happened</label><select name="event_type" class="input">@foreach (config('opportunity.event_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="label">Channel</label><select name="channel" class="input"><option value="">—</option>@foreach (config('opportunity.channels') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="label">When</label><input name="occurred_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" class="input"></div>
            </div>
            <div><label class="label">Notes</label><textarea name="notes" rows="2" class="input"></textarea></div>
            <button class="btn-primary">Log</button>
        </form>
        <div class="space-y-2">
            @forelse ($opportunity->outreachEvents as $ev)
                <div class="flex gap-3 border-b border-slate-100 pb-2 text-sm last:border-0">
                    <div class="w-28 shrink-0 text-xs text-slate-500">{{ $ev->occurred_at->format('M j, H:i') }}</div>
                    <div><span class="font-medium">{{ config('opportunity.event_types')[$ev->event_type] ?? $ev->event_type }}</span>
                        @if ($ev->channel) <span class="text-slate-500">via {{ config('opportunity.channels')[$ev->channel] ?? $ev->channel }}</span>@endif
                        @if ($ev->notes)<div class="text-slate-600">{{ $ev->notes }}</div>@endif</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Nothing logged yet.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Right column --}}
<div class="space-y-6">
    {{-- Assessment --}}
    <form method="POST" action="{{ route('opportunities.assess', $opportunity) }}" class="card space-y-4 p-5">
        @csrf @method('PUT')
        <div>
            <h2 class="font-medium">Assessment</h2>
            <p class="text-xs text-slate-500">Score each criterion 0–100. Leave blank if unknown. Weights come from settings; the total is derived.</p>
        </div>
        @foreach (config('opportunity.criteria') as $key => $label)
            <div>
                <div class="flex items-center justify-between gap-2">
                    <label class="text-sm font-medium" for="s_{{ $key }}">{{ $label }}</label>
                    <span class="text-xs text-slate-500">{{ $weights[$key] ?? 0 }}%</span>
                </div>
                <input id="s_{{ $key }}" name="scores[{{ $key }}][score]" type="number" min="0" max="100" class="input mt-1" value="{{ old("scores.$key.score", $scores[$key]->score ?? '') }}">
                <input name="scores[{{ $key }}][reasoning]" placeholder="Why?" class="input mt-1 text-xs" value="{{ old("scores.$key.reasoning", $scores[$key]->reasoning ?? '') }}">
            </div>
        @endforeach
        <div>
            <label class="label">Confidence in the match</label>
            <select name="confidence" class="input"><option value="">—</option>@foreach (['low', 'moderate', 'high'] as $c)<option value="{{ $c }}" @selected($opportunity->confidence === $c)>{{ ucfirst($c) }}</option>@endforeach</select>
        </div>
        <div>
            <label class="label">Recommended action</label>
            <textarea name="recommended_action" rows="2" class="input">{{ old('recommended_action', $opportunity->recommended_action) }}</textarea>
        </div>
        <div>
            <label class="label">Best channel</label>
            <select name="recommended_channel" class="input"><option value="">—</option>@foreach (config('opportunity.channels') as $k => $l)<option value="{{ $k }}" @selected($opportunity->recommended_channel === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div>
            <label class="label">Why that channel</label>
            <textarea name="channel_rationale" rows="2" class="input">{{ old('channel_rationale', $opportunity->channel_rationale) }}</textarea>
        </div>
        <button class="btn-primary w-full justify-center">Save assessment</button>
    </form>

    {{-- Follow-ups --}}
    <div class="card p-5" x-data="{ open: false }">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-medium">Follow-ups</h2>
            <button type="button" @click="open = !open" class="btn-secondary" x-text="open ? 'Close' : 'Add'"></button>
        </div>
        <form x-show="open" x-cloak method="POST" action="{{ route('follow-ups.store', $opportunity) }}" class="mb-4 space-y-2 rounded-md border border-slate-200 bg-slate-50 p-3">
            @csrf
            <input name="title" required placeholder="e.g. Follow up by email" class="input">
            <input name="due_at" type="datetime-local" required value="{{ now()->addDays(3)->setTime(9, 0)->format('Y-m-d\TH:i') }}" class="input">
            <textarea name="notes" rows="2" placeholder="Notes" class="input"></textarea>
            <button class="btn-primary">Save</button>
        </form>
        <div class="space-y-2">
            @forelse ($opportunity->followUps->sortBy('due_at') as $f)
                <div class="flex items-start justify-between gap-2 text-sm">
                    <div class="{{ $f->completed_at ? 'text-slate-400 line-through' : '' }}">
                        <div class="font-medium">{{ $f->title }}</div>
                        <div class="text-xs {{ ! $f->completed_at && $f->due_at->isPast() ? 'text-red-600' : 'text-slate-500' }}">{{ $f->due_at->format('M j, H:i') }}</div>
                        @if ($f->notes)<div class="text-xs text-slate-500">{{ $f->notes }}</div>@endif
                    </div>
                    <div class="flex shrink-0 gap-2 text-xs">
                        <form method="POST" action="{{ route('follow-ups.complete', $f) }}">@csrf @method('PATCH')<button class="text-indigo-600 hover:underline">{{ $f->completed_at ? 'Reopen' : 'Done' }}</button></form>
                        <form method="POST" action="{{ route('follow-ups.destroy', $f) }}">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Delete</button></form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">None set.</p>
            @endforelse
        </div>
    </div>

    {{-- Activity --}}
    <div class="card p-5">
        <h2 class="mb-3 font-medium">Activity</h2>
        <div class="space-y-2">
            @forelse ($activity as $a)
                <div class="text-xs"><span class="text-slate-400">{{ $a->created_at->format('M j, H:i') }}</span> · {{ $a->description ?? $a->action }}</div>
            @empty
                <p class="text-sm text-slate-500">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
</div>
@endsection
