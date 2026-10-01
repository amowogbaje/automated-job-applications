@extends('layouts.app')

@section('title', 'Jobs')
@section('container', 'max-w-5xl')

@section('content')
@php
    $tierStyles = [
        'strong' => ['Strong match', 'bg-forest text-white'],
        'good' => ['Good match', 'bg-forest-100 text-forest-dark'],
        'weak' => ['Weak match', 'bg-line text-ink/60'],
    ];
    $hasProfileSkills = ! empty($profile->required_skills) || ! empty($profile->keywords);
@endphp

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="font-serif text-3xl font-semibold">Your job feed</h1>
            <p class="text-sm text-ink/60 mt-1">{{ $jobs->total() }} matching {{ Str::plural('role', $jobs->total()) }}, {{ $sort === 'recent' ? 'newest first' : 'best match first' }}</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-forest hover:text-forest-dark"><x-icon name="target" class="w-4 h-4" /> Tune my matching</a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white border border-line rounded-2xl shadow-card p-4 mb-6 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto_auto] items-end" x-data="{ auto() { this.$el.requestSubmit() } }">
        <div>
            <label for="q" class="block text-xs font-medium text-ink/55 mb-1">Search</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 text-ink/35 absolute left-3 top-1/2 -translate-y-1/2" />
                <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="Title, company or skill" class="input pl-9">
            </div>
        </div>
        <div>
            <label for="window" class="block text-xs font-medium text-ink/55 mb-1">Posted</label>
            <select id="window" name="window" class="input" x-on:change="auto()">
                <option value="24" @selected($window == '24')>Last 24 hours</option>
                <option value="72" @selected($window == '72')>Last 3 days</option>
                <option value="168" @selected($window == '168')>Last 7 days</option>
                <option value="all" @selected($window == 'all')>All time</option>
            </select>
        </div>
        <div>
            <label for="tier" class="block text-xs font-medium text-ink/55 mb-1">Match quality</label>
            <select id="tier" name="tier" class="input" x-on:change="auto()">
                <option value="all" @selected($tier === 'all')>All matches</option>
                <option value="good" @selected($tier === 'good')>Good and strong</option>
                <option value="strong" @selected($tier === 'strong')>Strong only</option>
            </select>
        </div>
        <div>
            <label for="sort" class="block text-xs font-medium text-ink/55 mb-1">Sort</label>
            <select id="sort" name="sort" class="input" x-on:change="auto()">
                <option value="match" @selected($sort === 'match')>Best match</option>
                <option value="recent" @selected($sort === 'recent')>Newest</option>
            </select>
        </div>
        <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors">Apply</button>
    </form>

    @if (! $hasProfileSkills)
        <div class="mb-6 rounded-2xl border border-gold/30 bg-gold-light px-5 py-4 text-sm">
            Your feed is generic until you tell us your skills. <a href="{{ route('resume.upload') }}" class="font-medium text-forest underline">Upload your CV</a> or <a href="{{ route('profile.edit') }}" class="font-medium text-forest underline">add skills to your profile</a>.
        </div>
    @endif

    @forelse ($jobs as $job)
        @php [$tierLabel, $tierClass] = $tierStyles[$job->match_tier] ?? ['Match', 'bg-line text-ink/60']; @endphp
        <article class="group bg-white border border-line rounded-2xl shadow-card px-6 py-5 mb-4 hover:border-forest/30 transition-colors" x-data="{ more: false, why: false }">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-serif text-lg font-semibold leading-snug">
                        <a href="{{ $job->url }}" target="_blank" rel="noopener" class="hover:text-forest transition-colors">{{ $job->title }}</a>
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink/60 mt-1">
                        <span class="inline-flex items-center gap-1"><x-icon name="building" class="w-3.5 h-3.5 text-ink/35" /> {{ $job->company ?? 'Unknown company' }}</span>
                        <span class="inline-flex items-center gap-1"><x-icon name="pin" class="w-3.5 h-3.5 text-ink/35" /> {{ $job->location ?? ($job->is_remote ? 'Remote' : 'Not listed') }}</span>
                        <span class="inline-flex items-center gap-1"><x-icon name="clock" class="w-3.5 h-3.5 text-ink/35" /> {{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}</span>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <span class="inline-block rounded-full text-xs font-medium px-3 py-1 {{ $tierClass }}">{{ $tierLabel }}</span>
                    <p class="text-[11px] text-ink/40 mt-1">score {{ $job->match_score }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 mt-4">
                <span class="rounded-full bg-forest-light text-forest-dark text-xs px-2.5 py-1">{{ $job->source }}</span>
                @if ($job->ats)
                    <span class="rounded-full border border-forest-200 text-forest text-xs px-2.5 py-1">{{ ucfirst($job->ats) }} board</span>
                @endif
                @foreach ($job->matched_keywords ?? [] as $kw)
                    @php $inTitle = in_array($kw, $job->match_title_hits ?? [], true); $generic = in_array($kw, $job->match_generic ?? [], true); @endphp
                    <span class="rounded-full text-xs px-2.5 py-1 {{ $inTitle ? 'bg-gold text-white font-medium' : ($generic ? 'bg-line/70 text-ink/55' : 'bg-gold-light text-gold') }}"
                          title="{{ $inTitle ? 'In the job title' : ($generic ? 'Broad skill: adds little to the score' : 'In the description') }}">{{ $kw }}</span>
                @endforeach
                <button type="button" x-on:click="why = !why" class="text-xs text-forest hover:text-forest-dark font-medium ml-1">Why this match?</button>
            </div>

            @if (! empty($job->match_warnings))
                <div class="mt-3 flex items-start gap-2 rounded-lg bg-rust-light text-rust text-xs px-3 py-2">
                    <x-icon name="alert" class="w-4 h-4 shrink-0" /> <span>{{ $job->match_warnings[0] }}. Check you're eligible before applying.</span>
                </div>
            @endif

            <div x-show="why" x-cloak x-transition class="mt-3 rounded-xl bg-paper border border-line px-4 py-3 text-xs text-ink/65 leading-relaxed">
                @php $titleHits = $job->match_title_hits ?? []; @endphp
                @if ($titleHits)
                    <p><strong class="text-ink/80">In the title:</strong> {{ implode(', ', $titleHits) }}. These weigh the most.</p>
                @endif
                <p><strong class="text-ink/80">Matched in the text:</strong> {{ implode(', ', array_diff($job->matched_keywords ?? [], $job->match_generic ?? [])) ?: 'nothing core' }}.</p>
                @if (! empty($job->match_generic))
                    <p><strong class="text-ink/80">Broad skills:</strong> {{ implode(', ', $job->match_generic) }}. They show up but never qualify a role by themselves.</p>
                @endif
            </div>

            @if ($job->description)
                <div class="mt-3">
                    <button type="button" x-on:click="more = !more" class="text-sm text-ink/55 hover:text-ink inline-flex items-center gap-1">
                        <span x-text="more ? 'Hide description' : 'Read description'"></span>
                        <x-icon name="chevron-down" class="w-4 h-4 transition-transform" x-bind:class="more ? 'rotate-180' : ''" />
                    </button>
                    <p x-show="more" x-cloak x-collapse class="text-sm text-ink/70 mt-2 whitespace-pre-line leading-relaxed">{{ Str::limit($job->description, 1500) }}</p>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 pt-4 mt-4 border-t border-line text-sm">
                <a href="{{ $job->apply_url ?: $job->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-4 py-1.5 transition-colors">
                    Apply <x-icon name="external" class="w-3.5 h-3.5" />
                </a>
                <form method="POST" action="{{ route('jobs.applied', $job) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 font-medium text-forest hover:text-forest-dark"><x-icon name="check" class="w-4 h-4" /> Mark applied</button>
                </form>

                @if ($job->description)
                    <a href="{{ route('jobs.resume', $job) }}" target="_blank" class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-forest">
                        <x-icon name="doc" class="w-4 h-4" /> {{ $job->tailored_resume_path ? 'Tailored resume' : 'Generate resume' }}
                    </a>
                    <a href="{{ route('jobs.coverLetter', $job) }}" class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-forest">
                        <x-icon name="mail" class="w-4 h-4" /> {{ $job->tailored_cover_letter ? 'Cover letter' : 'Generate letter' }}
                    </a>
                @endif

                <form method="POST" action="{{ route('jobs.dismiss', $job) }}" class="ml-auto">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 text-ink/40 hover:text-rust"><x-icon name="x" class="w-4 h-4" /> Dismiss</button>
                </form>
            </div>
        </article>
    @empty
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <span class="mx-auto w-12 h-12 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="search" class="w-6 h-6" /></span>
            <p class="mt-4 font-medium">No matches in this window</p>
            <p class="text-sm text-ink/55 mt-1">Try a wider time range, or loosen your required skills on your profile.</p>
        </div>
    @endforelse

    <div class="mt-6">{{ $jobs->links() }}</div>
@endsection
