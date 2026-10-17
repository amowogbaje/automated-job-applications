@extends('layouts.app')

@section('title', 'Jobs')
@section('container', 'max-w-5xl')

@section('content')
@php
    $tierStyles = [
        'strong' => ['Strong match', 'bg-indigo-600 text-white'],
        'good' => ['Good match', 'bg-indigo-100 text-indigo-800'],
        'weak' => ['Weak match', 'bg-slate-100 text-slate-600'],
    ];
    $skillList = array_values(array_unique(array_merge((array) $profile->required_skills, (array) $profile->keywords)));
@endphp

@include('partials.dash-tabs', ['active' => 'jobs'])

<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold">Jobs for your skills</h1>
        <p id="feed-count" class="mt-1 text-sm text-slate-500">
            {{ $states->total() }} {{ $status === 'applied' ? 'applied' : 'open' }} {{ Str::plural('match', $states->total()) }}
            · {{ $counts['boards'] }} from job boards · {{ $counts['social'] }} from social posts
            @if ($counts['applied']) · {{ $counts['applied'] }} applied @endif
        </p>
    </div>
    <a href="{{ route('jobs.interests') }}" class="btn-secondary">Change skills</a>
</div>

@if (! $hasInterests)
    <div class="card mb-6 border-amber-200 bg-amber-50 p-5 text-sm">
        <p class="font-medium text-amber-900">Tell me which skills you want jobs for.</p>
        <p class="mt-1 text-amber-800">Your feed stays empty until you pick them, for example just <em>Laravel</em>. Nothing else is shown and nothing else is emailed.</p>
        <a href="{{ route('jobs.interests') }}" class="btn-primary mt-3">Choose skills</a>
    </div>
@elseif (! $profile->open_to_work)
    <div class="card mb-6 border-slate-300 bg-slate-100 p-4 text-sm text-slate-700">Your feed is paused (not open to work), so no new jobs are being matched. <a href="{{ route('jobs.interests') }}" class="font-medium text-indigo-700 underline">Turn it back on</a>.</div>
@else
    <p class="mb-4 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
        <span>Showing jobs for</span>
        @foreach (array_slice($skillList, 0, 10) as $skill)
            <span class="badge bg-indigo-50 text-indigo-700">{{ $skill }}</span>
        @endforeach
        @if (count($skillList) > 10)<span>+{{ count($skillList) - 10 }} more</span>@endif
    </p>
@endif

{{-- Filters --}}
<form method="GET" data-live="#feed, #feed-count" data-live-auto class="card mb-6 grid items-end gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto_auto_auto]">
    <div>
        <label for="q" class="label">Search</label>
        <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="Title, company or text" class="input">
    </div>
    <div>
        <label for="source" class="label">From</label>
        <select id="source" name="source" class="input">
            <option value="all" @selected($source === 'all')>Everywhere</option>
            <option value="boards" @selected($source === 'boards')>Job boards</option>
            <option value="social" @selected($source === 'social')>Social posts</option>
        </select>
    </div>
    <div>
        <label for="window" class="label">Posted</label>
        <select id="window" name="window" class="input">
            <option value="24" @selected($window == '24')>Last 24 hours</option>
            <option value="72" @selected($window == '72')>Last 3 days</option>
            <option value="168" @selected($window == '168')>Last 7 days</option>
            <option value="336" @selected($window == '336')>Last 14 days</option>
            <option value="all" @selected($window == 'all')>All time</option>
        </select>
    </div>
    <div>
        <label for="tier" class="label">Match quality</label>
        <select id="tier" name="tier" class="input">
            <option value="all" @selected($tier === 'all')>All matches</option>
            <option value="good" @selected($tier === 'good')>Good and strong</option>
            <option value="strong" @selected($tier === 'strong')>Strong only</option>
        </select>
    </div>
    <div>
        <label for="sort" class="label">Sort</label>
        <select id="sort" name="sort" class="input">
            <option value="match" @selected($sort === 'match')>Best match</option>
            <option value="recent" @selected($sort === 'recent')>Newest</option>
        </select>
    </div>
    <div>
        <label for="status" class="label">Show</label>
        <select id="status" name="status" class="input">
            <option value="open" @selected($status === 'open')>Open</option>
            <option value="applied" @selected($status === 'applied')>Applied</option>
        </select>
    </div>
</form>

<div id="feed" data-live-scope="#feed, #feed-count">
@forelse ($states as $state)
    @php
        $job = $state->job;
        $isSocial = $job->kind === 'social';
        [$tierLabel, $tierClass] = $tierStyles[$state->tier] ?? ['Match', 'bg-slate-100 text-slate-600'];
        $titleHits = $state->title_hits ?? [];
    @endphp
    <article class="card mb-4 p-5 hover:border-indigo-300" x-data="{ more: false, why: false, tr: false }">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold leading-snug">
                    <a href="{{ $job->url }}" target="_blank" rel="noopener" class="hover:text-indigo-700">
                        @if ($job->isTranslated())
                            <span x-show="!tr">{{ $job->title }}</span><span x-show="tr" x-cloak>{{ $job->title_translated }}</span>
                        @else
                            {{ $job->title }}
                        @endif
                    </a>
                </h2>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                    @if ($isSocial)
                        <span>{{ $job->social_platform === 'x' ? 'X' : ucfirst($job->social_platform ?? 'Social') }}@if ($job->social_author) · {{ $job->social_author }}@endif</span>
                    @else
                        <span>{{ $job->company ?? 'Unknown company' }}</span>
                    @endif
                    <span>{{ $job->location ?? ($job->is_remote ? 'Remote' : 'Location not listed') }}</span>
                    <span>{{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}</span>
                </div>
            </div>
            <div class="shrink-0 text-right">
                <span class="badge {{ $tierClass }}">{{ $tierLabel }}</span>
                <p class="mt-1 text-[11px] text-slate-400">score {{ $state->score }}</p>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-1.5">
            @if ($isSocial)
                <span class="badge bg-violet-100 text-violet-800">Social post</span>
            @else
                <span class="badge bg-slate-100 text-slate-700">{{ $job->source }}</span>
            @endif
            @if ($job->languageName())
                <span class="badge bg-amber-50 text-amber-700">{{ $job->languageName() }}</span>
                @if ($job->isTranslated())
                    <button type="button" x-on:click="tr = !tr" class="badge border border-indigo-200 text-indigo-700 hover:bg-indigo-600 hover:text-white" x-text="tr ? 'Show original' : 'Show translation'"></button>
                @elseif ($job->translation_status === 'pending')
                    <span class="badge border border-slate-200 text-slate-500" title="Translations are prepared automatically within about 5 minutes.">Translation queued…</span>
                @endif
            @endif
            @foreach ($state->matched ?? [] as $kw)
                @php $inTitle = in_array($kw, $titleHits, true); @endphp
                <span class="badge {{ $inTitle ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700' }}" title="{{ $inTitle ? 'In the job title' : 'In the description' }}">{{ $kw }}</span>
            @endforeach
            <button type="button" x-on:click="why = !why" class="ml-1 text-xs font-medium text-indigo-600 hover:text-indigo-800">Why this match?</button>
        </div>

        @include('partials.also-posted', ['job' => $job])

        @if (! empty($state->warnings))
            <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{{ $state->warnings[0] }}. Check you're eligible before applying.</div>
        @endif

        <div x-show="why" x-cloak x-transition class="mt-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
            @if ($titleHits)<p><strong class="text-slate-800">In the title:</strong> {{ implode(', ', $titleHits) }}. These weigh the most.</p>@endif
            <p><strong class="text-slate-800">Matched:</strong> {{ implode(', ', $state->matched ?? []) ?: 'nothing' }}.</p>
            <p class="mt-1 text-slate-500">Matched against the skills you chose on <a href="{{ route('jobs.interests') }}" class="underline">Job interests</a>.</p>
        </div>

        @if ($isSocial && $job->apply_instructions)
            <p class="mt-3 text-sm text-slate-700">{{ $job->apply_instructions }}</p>
        @endif
        @if ($isSocial && $job->apply_method === 'email' && $job->apply_email)
            <p class="mt-1 text-sm text-slate-600">Apply by email: <strong>{{ $job->apply_email }}</strong>@if ($job->apply_subject) <span class="text-slate-400">· subject “{{ $job->apply_subject }}”</span>@endif</p>
        @endif

        @if ($job->description && ! $isSocial)
            <div class="mt-3">
                <button type="button" x-on:click="more = !more" class="text-sm text-slate-500 hover:text-slate-800" x-text="more ? 'Hide description' : 'Read description'"></button>
                <div x-show="more" x-cloak x-collapse>
                    <p x-show="!tr" class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ Str::limit($job->description, 1500) }}</p>
                    @if ($job->isTranslated() && $job->description_translated)
                        <div x-show="tr" x-cloak>
                            <p class="mt-2 text-xs text-amber-700">Machine translation{{ $job->translation_provider ? ' (' . $job->translation_provider . ')' : '' }}: check the original before applying.</p>
                            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ Str::limit($job->description_translated, 1500) }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-100 pt-4 text-sm">
            <a href="{{ $isSocial ? $job->url : ($job->apply_url ?: $job->url) }}" target="_blank" rel="noopener" class="btn-primary">{{ $isSocial ? 'Open post' : 'Apply' }} ↗</a>

            @if ($status === 'open')
                <form method="POST" action="{{ route('jobs.applied', $job) }}" data-live-remove="article">
                    @csrf
                    <button type="submit" class="font-medium text-indigo-600 hover:text-indigo-800">Mark applied</button>
                </form>
            @else
                <span class="text-green-700">Applied {{ $state->applied_at?->diffForHumans() }}</span>
            @endif

            @if ($job->description)
                <a data-no-live href="{{ route('jobs.resume', $job) }}" target="_blank" class="font-medium text-slate-600 hover:text-indigo-700">{{ $job->tailored_resume_path ? 'Tailored resume' : 'Tailor resume' }}</a>
                <a href="{{ route('jobs.coverLetter', $job) }}" class="font-medium text-slate-600 hover:text-indigo-700">{{ $job->tailored_cover_letter ? 'Cover letter' : 'Write cover letter' }}</a>
            @endif

            <form method="POST" action="{{ route('jobs.dismiss', $job) }}" class="ml-auto" data-live-remove="article">
                @csrf
                <button type="submit" class="text-slate-400 hover:text-red-600">Dismiss</button>
            </form>
        </div>
    </article>
@empty
    <div class="card px-6 py-14 text-center">
        <p class="font-medium">{{ $hasInterests ? 'No matches here yet' : 'Nothing to show yet' }}</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
            @if (! $hasInterests)
                Pick the skills you want jobs for and the feed fills from the last {{ \App\Services\Matching\JobMatchService::WINDOW_DAYS }} days straight away.
            @else
                New jobs are matched after every fetch (hourly). Try a wider time range, or add another skill on Job interests.
            @endif
        </p>
    </div>
@endforelse

<div class="mt-6">{{ $states->links() }}</div>
</div>
@endsection
