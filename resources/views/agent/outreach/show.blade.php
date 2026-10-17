@extends('agent.layout')
@section('agent_title', 'Outreach Studio')

@section('agent')
@php
    $approachInfo = [
        'direct' => ['Direct', 'Says who you are and what you offer, plainly.'],
        'problem_solving' => ['Problem-solving', 'Opens with one evidenced observation about them.'],
        'experience_led' => ['Experience-led', 'Opens with a relevant project of yours.'],
    ];
    $opts = [];
    foreach ($channels as $key => $ch) {
        $first = $ch['contacts']->firstWhere('is_preferred', true) ?? $ch['contacts']->first();
        $opts[$key] = [
            'contact_id' => $first?->id, 'service_id' => $opportunity->primary_service_id,
            'project_ids' => array_values($defaultProjects), 'cv_version_id' => $defaultCvId,
            'notes' => '', 'ignore_restrictions' => false,
        ];
    }
@endphp

<div id="studio" x-data="studio(@js(['channel' => $channel, 'opts' => $opts, 'generateUrl' => route('agent.outreach.generate', $opportunity)]))">

    {{-- Header --}}
    <div class="mb-5">
        <a href="{{ route('agent.opportunities.show', $opportunity) }}" class="text-xs text-forest-600 hover:underline">← {{ $opportunity->title }}</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold">Outreach Studio</h1>
            <x-agent.status-badge :status="$opportunity->status" />
        </div>
        <p class="text-sm text-slate-500">{{ $company->name }}@if ($opportunity->primaryService) · {{ $opportunity->primaryService->name }}@endif · three different drafts per channel. You review, copy and send everything yourself.</p>
    </div>

    @if ($company->do_not_contact)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">This company is marked <strong>do not contact</strong>@if ($company->do_not_contact_reason): {{ $company->do_not_contact_reason }}@endif. Drafting is switched off.</div>
    @endif
    @unless ($aiReady)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">No AI provider is ready. <a class="underline" href="{{ route('agent.providers.index') }}">Add an API key on the Providers page</a> to generate drafts.</div>
    @endunless
    @if ($opportunity->evidence->isEmpty())
        <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">No evidence is saved for this opportunity yet, so drafts cannot make verified observations about the company. <a class="text-forest-600 underline" href="{{ route('agent.opportunities.show', $opportunity) }}">Run research or add evidence</a> for better drafts.</div>
    @endif
    @if ($recommendation['channel'])
        <p class="mb-4 text-sm text-slate-600"><span class="font-medium">Suggested channel: {{ $channels[$recommendation['channel']]['label'] }}.</span> {{ $recommendation['rationale'] }}</p>
    @else
        <p class="mb-4 text-sm text-slate-600">{{ $recommendation['rationale'] }} You can still prepare a draft and send it once you find a route.</p>
    @endif

    {{-- Channel tabs --}}
    <div id="tabs" class="mb-4 flex flex-wrap gap-1 border-b border-line">
        @foreach ($channels as $key => $ch)
            @php $n = $ch['drafts']->where('status', '!=', 'discarded')->count(); @endphp
            <button type="button" x-on:click="channel = '{{ $key }}'"
                    :class="channel === '{{ $key }}' ? 'border-forest text-forest' : 'border-transparent text-ink/60 hover:text-ink'"
                    class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium">
                {{ $ch['label'] }}
                @if ($ch['contacts']->isNotEmpty())<span class="h-1.5 w-1.5 rounded-full bg-green-500" title="A contact route is saved"></span>@endif
                @if ($n)<span class="badge bg-slate-100 text-slate-600">{{ $n }}</span>@endif
            </button>
        @endforeach
    </div>

    @foreach ($channels as $key => $ch)
    <section x-show="channel === '{{ $key }}'" x-cloak class="space-y-5">

        {{-- Options --}}
        <div class="card p-5">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label">{{ $key === 'phone' ? 'Phone number' : 'Send to' }}</label>
                    @if ($ch['contacts']->isNotEmpty())
                        <select x-model.number="opts.{{ $key }}.contact_id" class="input">
                            @foreach ($ch['contacts'] as $c)
                                <option value="{{ $c->id }}">{{ $c->value }}{{ $c->label ? ' — '.$c->label : '' }}{{ $c->person_name ? ' ('.$c->person_name.')' : '' }}</option>
                            @endforeach
                        </select>
                        @php $restricted = $ch['contacts']->filter(fn ($c) => $c->restrictions); @endphp
                        @foreach ($restricted as $c)
                            <p class="mt-1 text-xs text-amber-700" x-show="opts.{{ $key }}.contact_id === {{ $c->id }}">Their note: {{ $c->restrictions }}</p>
                        @endforeach
                        @if ($restricted->isNotEmpty())
                            <label class="mt-1 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" x-model="opts.{{ $key }}.ignore_restrictions"> It's fine to proceed despite a "no unsolicited" note</label>
                        @endif
                    @else
                        <div class="rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500">No public {{ strtolower($ch['label']) }} route saved. You can still draft; add the contact on the opportunity page when you find one.</div>
                    @endif
                </div>
                <div>
                    <label class="label">Service to lead with</label>
                    <select x-model.number="opts.{{ $key }}.service_id" class="input">
                        <option value="">General</option>
                        @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Projects the drafts may cite (max 3)</label>
                    <div class="max-h-32 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2 text-sm">
                        @forelse ($projects as $p)
                            <label class="flex items-center gap-2"><input type="checkbox" value="{{ $p->id }}" x-model.number="opts.{{ $key }}.project_ids" :disabled="opts.{{ $key }}.project_ids.length >= 3 && !opts.{{ $key }}.project_ids.includes({{ $p->id }})"> {{ $p->name }}</label>
                        @empty
                            <span class="text-slate-500">No projects yet. <a class="text-forest-600 underline" href="{{ route('agent.projects.index') }}">Add one</a>.</span>
                        @endforelse
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="label">CV to attach or link</label>
                        <select x-model.number="opts.{{ $key }}.cv_version_id" class="input">
                            <option value="">None</option>
                            @foreach ($cvs as $cv)<option value="{{ $cv->id }}">{{ $cv->name }}{{ $cv->is_default ? ' (default)' : '' }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Notes for the writer <span class="font-normal text-slate-400">(tone only, optional)</span></label>
                        <input x-model="opts.{{ $key }}.notes" maxlength="500" placeholder="e.g. keep it warm, mention I work in WAT timezone" class="input">
                    </div>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary disabled:opacity-50" :disabled="busy || {{ ($company->do_not_contact || ! $aiReady) ? 'true' : 'false' }}" x-on:click="generate('{{ $key }}')">
                    <span x-show="!(busy && working === '{{ $key }}')">{{ $ch['drafts']->isEmpty() ? 'Write 3 drafts' : 'Write 3 new drafts' }}</span>
                    <span x-show="busy && working === '{{ $key }}'" x-cloak>Writing…</span>
                </button>
                <span class="text-xs text-slate-500">Up to about {{ $ch['max_words'] }} words each. Earlier unreviewed drafts are retired; edited, selected and sent ones are kept.</span>
            </div>
            <p x-show="error && working === '{{ $key }}'" x-cloak x-text="error" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"></p>
        </div>

        {{-- Drafts --}}
        <div id="grid-{{ $key }}" class="space-y-6">
            @php
                $groups = $ch['drafts']->groupBy(fn ($d) => $d->contact_id.'-'.$d->version)->sortByDesc(fn ($g) => $g->first()->version)->values();
            @endphp
            @forelse ($groups as $gi => $group)
                @php
                    $first = $group->first();
                    $sorted = $group->sortBy(fn ($d) => array_search($d->approach, \App\Services\Agent\Outreach\OutreachChannels::APPROACHES))->values();
                    $cv = $first->cvVersion;
                @endphp
                <details @if ($gi === 0) open @endif class="group">
                    <summary class="mb-3 flex cursor-pointer flex-wrap items-center gap-2 text-sm">
                        <span class="font-medium">Version {{ $first->version }}</span>
                        <span class="text-slate-500">{{ $first->created_at->diffForHumans() }}</span>
                        @if ($first->ai_model)<span class="badge bg-slate-100 text-slate-500">{{ $first->ai_model }}</span>@endif
                    </summary>

                    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-md bg-slate-50 px-3 py-2 text-sm">
                        @if ($first->contact)
                            <span class="font-medium">To:</span>
                            <span class="break-all" x-data="{ c: false }">{{ $first->contact->value }}
                                <button type="button" class="ml-1 text-xs text-forest-600 hover:underline" x-on:click="copyText(@js($first->contact->value)); c = true; setTimeout(() => c = false, 1500)" x-text="c ? 'Copied' : 'Copy'"></button></span>
                            <a class="text-xs text-forest-600 hover:underline" target="_blank" rel="noopener" href="{{ $first->contact->source_url }}">published at {{ parse_url($first->contact->source_url, PHP_URL_HOST) }}</a>
                        @else
                            <span class="text-slate-500">No contact saved for this set.</span>
                        @endif
                        @if ($cv)
                            <span class="font-medium">Attach:</span>
                            @if ($cv->file_path)<a data-no-live class="text-forest-600 hover:underline" href="{{ route('agent.cvs.download', $cv) }}">{{ $cv->name }}</a>
                            @elseif ($cv->external_url)<a class="text-forest-600 hover:underline" target="_blank" rel="noopener" href="{{ $cv->external_url }}">{{ $cv->name }} (link)</a>
                            @else<span>{{ $cv->name }}</span>@endif
                        @endif
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        @foreach ($sorted as $d)
                            @php
                                $sigs = $ch['signature'] === null ? collect() : $signatures->filter(fn ($s) => in_array($s->channel, [$ch['signature'], 'any'], true) || $s->id === $d->signature_id);
                                $payload = [
                                    'id' => $d->id, 'channel' => $key, 'status' => $d->status, 'selected' => (bool) $d->is_selected,
                                    'subject' => $d->subject, 'body' => $d->body, 'signature_id' => $d->signature_id, 'hasSubject' => (bool) $ch['subject'],
                                    'limit' => $ch['max_words'], 'sent' => $d->status === 'sent', 'edited' => (bool) $d->edited_at,
                                    'urls' => [
                                        'update' => route('agent.outreach.update', $d), 'select' => route('agent.outreach.select', $d), 'copied' => route('agent.outreach.copied', $d),
                                        'discard' => route('agent.outreach.discard', $d), 'sent' => route('agent.outreach.sent', $d), 'reset' => route('agent.outreach.reset', $d),
                                    ],
                                    'alt' => $d->alt_subjects ?? [],
                                    'sigBodies' => $sigs->mapWithKeys(fn ($s) => [$s->id => $s->body])->all(),
                                ];
                            @endphp
                            <article class="card flex flex-col p-4 {{ $d->is_selected ? 'ring-2 ring-forest' : '' }}" x-data="draftCard(@js($payload))" :class="selected ? 'ring-2 ring-forest' : ''">
                                <header class="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <h3 class="font-medium">{{ $approachInfo[$d->approach][0] ?? $d->approach }}</h3>
                                        <p class="text-xs text-slate-500">{{ $approachInfo[$d->approach][1] ?? '' }}</p>
                                    </div>
                                    <span class="badge shrink-0" :class="{'bg-green-100 text-green-700': status==='sent', 'bg-forest-50 text-forest-700': selected && status!=='sent', 'bg-amber-100 text-amber-700': status==='edited' && !selected, 'bg-slate-100 text-slate-600': status==='draft'}" x-text="status === 'sent' ? 'sent' : (selected ? 'selected' : (status === 'edited' ? 'edited' : 'draft'))"></span>
                                </header>

                                @if ($ch['subject'])
                                    <label class="label">Subject</label>
                                    <input x-model="subject" x-on:input="queueSave()" :disabled="sent" class="input mb-1">
                                    @if (! empty($d->alt_subjects))
                                        <div class="mb-2 flex flex-wrap gap-1" x-show="!sent">
                                            @foreach ($d->alt_subjects as $alt)
                                                <button type="button" class="badge bg-slate-100 text-slate-600 hover:bg-slate-200" title="Use this subject instead" x-on:click="subject = @js($alt); queueSave()">{{ $alt }}</button>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                                <label class="label">{{ $key === 'phone' ? 'Script' : 'Message' }}</label>
                                <textarea x-model="body" x-on:input="queueSave()" :disabled="sent" rows="11" class="input flex-1 font-sans leading-relaxed"></textarea>
                                <div class="mt-1 flex items-center justify-between text-xs">
                                    <span :class="words > limit * 1.25 ? 'text-red-600' : (words > limit ? 'text-amber-600' : 'text-slate-500')"><span x-text="words"></span> / ~{{ $ch['max_words'] }} words</span>
                                    <span class="text-slate-400" x-text="saving ? 'Saving…' : (savedAt ? 'Saved' : '')"></span>
                                </div>

                                @if ($d->warnings)
                                    <div class="mt-2 space-y-1">@foreach ($d->warnings as $w)<p class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">{{ $w }}</p>@endforeach</div>
                                @endif

                                {{-- What the draft says about them, and where that came from --}}
                                @if (! empty($d->claims))
                                    <details class="mt-2 text-xs">
                                        <summary class="cursor-pointer text-slate-600">Check the facts used ({{ count($d->claims) }})</summary>
                                        <ul class="mt-1 space-y-2">
                                            @foreach ($d->claims as $claim)
                                                @php $ev = $evidenceById[$claim['evidence_id']] ?? null; @endphp
                                                <li class="rounded border border-slate-200 p-2">
                                                    <div class="italic text-slate-700">“{{ $claim['quote'] }}”</div>
                                                    @if ($ev)
                                                        <div class="mt-1 text-slate-500">Evidence: {{ $ev->establishes ?: $ev->finding }} <span class="badge bg-slate-100">{{ $ev->confidence }}</span>
                                                            <a class="text-forest-600 hover:underline" target="_blank" rel="noopener" href="{{ $ev->source_url }}">source</a></div>
                                                    @else
                                                        <div class="text-red-600">The evidence row was removed.</div>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <p class="mt-2 text-xs text-slate-400">This draft makes no specific claims about the company.</p>
                                @endif
                                @if ($d->portfolioProject)
                                    <p class="mt-2 text-xs text-slate-500">Cites project: <span class="font-medium">{{ $d->portfolioProject->name }}</span>@if ($d->portfolioProject->url) · <a class="text-forest-600 hover:underline" target="_blank" rel="noopener" href="{{ $d->portfolioProject->url }}">link</a>@endif</p>
                                @endif

                                @if ($sigs->isNotEmpty())
                                    <div class="mt-3" x-show="!sent">
                                        <label class="label">Signature</label>
                                        <select x-model.number="signature_id" x-on:change="queueSave(true)" class="input">
                                            <option value="">None</option>
                                            @foreach ($sigs as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                        </select>
                                    </div>
                                @endif

                                <p x-show="message" x-cloak x-text="message" class="mt-2 rounded bg-red-50 px-2 py-1 text-xs text-red-700"></p>

                                {{-- Actions --}}
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button type="button" class="btn-primary" x-on:click="copy()" x-text="copied ? 'Copied ✓' : '{{ $ch['subject'] ? 'Copy message' : 'Copy' }}'"></button>
                                    @if ($ch['subject'])<button type="button" class="btn-secondary" x-on:click="copySubject()" x-text="copiedSubject ? 'Copied ✓' : 'Copy subject'"></button>@endif
                                    <button type="button" class="btn-secondary" x-show="!sent && !selected" x-on:click="act('select')">Select</button>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs" x-show="!sent">
                                    <button type="button" class="text-forest-600 hover:underline" x-on:click="askSent = !askSent">I've sent this</button>
                                    <button type="button" class="text-slate-500 hover:underline" x-show="edited" x-on:click="act('reset')">Reset to original</button>
                                    <button type="button" class="ml-auto text-red-600 hover:underline" x-on:click="if (confirm('Discard this draft?')) act('discard')">Discard</button>
                                </div>
                                <div x-show="askSent && !sent" x-cloak class="mt-3 space-y-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                                    <p class="text-xs text-slate-500">Logs the message, moves the opportunity to “Contacted”, and sets a reminder. Nothing is sent from here.</p>
                                    <label class="label">Remind me to follow up in</label>
                                    <select x-model.number="followDays" class="input"><option value="0">No reminder</option><option value="3">3 days</option><option value="5">5 days</option><option value="7">1 week</option><option value="14">2 weeks</option></select>
                                    <input x-model="sentNotes" placeholder="Note (optional)" class="input">
                                    <button type="button" class="btn-primary" x-on:click="act('sent')">Confirm sent</button>
                                </div>
                                <p x-show="sent" class="mt-3 text-xs text-green-700">Sent{{ $d->sent_at ? ' '.$d->sent_at->format('M j, H:i') : '' }}. Log any reply on the opportunity page.</p>
                            </article>
                        @endforeach
                    </div>
                </details>
            @empty
                <div class="card p-8 text-center text-sm text-slate-500">No {{ strtolower($ch['label']) }} drafts yet. Choose the options above and write three.</div>
            @endforelse
        </div>
    </section>
    @endforeach
</div>
@endsection
