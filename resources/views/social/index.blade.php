@extends('layouts.app')

@section('title', 'Social jobs')
@section('container', 'max-w-5xl')

@section('content')
@php
    $platforms = ['x' => ['X', 'bg-ink text-white'], 'mastodon' => ['Mastodon', 'bg-[#6364FF] text-white'], 'hackernews' => ['Hacker News', 'bg-[#FF6600] text-white'], 'bluesky' => ['Bluesky', 'bg-[#1185FE] text-white'], 'lemmy' => ['Lemmy', 'bg-[#00BC8C] text-white'], 'reddit' => ['Reddit', 'bg-[#FF4500] text-white'], 'github' => ['GitHub', 'bg-[#24292F] text-white']];
    $windows = ['6' => 'Last 6 hours', '24' => 'Last 24 hours', '72' => 'Last 3 days', '168' => 'Last 7 days', 'all' => 'Everything stored'];
    $tierStyles = ['strong' => ['Strong match', 'bg-forest text-white'], 'good' => ['Good match', 'bg-forest-100 text-forest-dark'], 'weak' => ['Weak match', 'bg-line text-ink/60']];
    $f = $filters;
@endphp

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="font-serif text-3xl font-semibold">Social job posts</h1>
            <p class="text-sm text-ink/60 mt-1">{{ $posts->total() }} fresh {{ Str::plural('post', $posts->total()) }} matching your skills, {{ $f['sort'] === 'match' ? 'best match first' : 'newest first' }}</p>
        </div>
        <a href="{{ route('profile.edit', ['tab' => 'autoapply']) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-forest hover:text-forest-dark"><x-icon name="target" class="w-4 h-4" /> Social settings</a>
    </div>

    {{-- Day / platform filters --}}
    <form method="GET" class="bg-white border border-line rounded-2xl shadow-card p-4 mb-6 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto_auto] items-end" x-data="{ auto() { this.$el.requestSubmit() } }">
        <div>
            <label for="q" class="block text-xs font-medium text-ink/55 mb-1">Search</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 text-ink/35 absolute left-3 top-1/2 -translate-y-1/2" />
                <input type="text" id="q" name="q" value="{{ $f['keyword'] }}" placeholder="Skill, company or word in the post" class="input pl-9">
            </div>
        </div>
        <div>
            <label for="window" class="block text-xs font-medium text-ink/55 mb-1">Posted</label>
            <select id="window" name="window" class="input" x-on:change="auto()">
                @foreach ($windows as $v => $l)<option value="{{ $v }}" @selected($f['window'] == $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="platform" class="block text-xs font-medium text-ink/55 mb-1">Platform</label>
            <select id="platform" name="platform" class="input" x-on:change="auto()">
                <option value="all">All platforms</option>
                @foreach ($platforms as $v => [$l]) <option value="{{ $v }}" @selected($f['platform'] === $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="how" class="block text-xs font-medium text-ink/55 mb-1">How to apply</label>
            <select id="how" name="how" class="input" x-on:change="auto()">
                <option value="all" @selected($f['how'] === 'all')>Any</option>
                <option value="email" @selected($f['how'] === 'email')>By email</option>
                <option value="link" @selected($f['how'] === 'link')>Via link / DM</option>
            </select>
        </div>
        <div class="flex items-center gap-3">
            <select name="sort" class="input" x-on:change="auto()" aria-label="Sort">
                <option value="recent" @selected($f['sort'] === 'recent')>Newest</option>
                <option value="match" @selected($f['sort'] === 'match')>Best match</option>
            </select>
            <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors">Apply</button>
        </div>
        <label class="sm:col-span-5 flex items-center gap-2 text-xs text-ink/55">
            <input type="checkbox" name="applied" value="1" @checked($f['showApplied']) x-on:change="auto()" class="rounded border-line text-forest focus:ring-forest"> Include posts I've already applied to
        </label>
    </form>

    @if (! $profile->social_jobs_enabled)
        <div class="mb-6 rounded-2xl border border-gold/30 bg-gold-light px-5 py-4 text-sm">Social jobs are switched off for your account. <a href="{{ route('profile.edit', ['tab' => 'autoapply']) }}" class="font-medium text-forest underline">Turn them on</a> and posts matching your skills will start appearing.</div>
    @endif

    @if (! in_array('x', $sources, true) || ! $xEnabled)
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-line bg-white px-5 py-3.5 text-sm text-ink/65">
            <x-icon name="alert" class="w-[18px] h-[18px] mt-0.5 shrink-0 text-gold" />
            <span>X isn't connected on this install (its API is paid per post read), so this feed currently comes from {{ collect($sources)->map(fn ($s) => $platforms[$s][0] ?? $s)->implode(' and ') ?: 'no sources' }}. See <strong>integration.md</strong> to add an X token.</span>
        </div>
    @endif

    @if ($errors->has('send'))
        <div class="mb-4 rounded-xl bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">{{ $errors->first('send') }}</div>
    @endif

    @forelse ($posts as $job)
        @php
            [$pName, $pClass] = $platforms[$job->social_platform] ?? [ucfirst($job->social_platform ?? 'Social'), 'bg-line text-ink'];
            [$tierLabel, $tierClass] = $tierStyles[$job->match_tier] ?? ['Match', 'bg-line text-ink/60'];
            $applied = $job->stateFor(auth()->id())->is_applied;
            $emailable = $job->apply_method === 'email' && $job->apply_email && ! $job->needs_manual_input;
            $fresh = $job->posted_at && $job->posted_at->gt(now()->subHours(3));
        @endphp
        <article class="bg-white border border-line rounded-2xl shadow-card px-6 py-5 mb-4 hover:border-forest/30 transition-colors {{ $applied ? 'opacity-70' : '' }}" x-data="{ full: false, tr: false, confirmSend: false }">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full px-2.5 py-1 font-medium {{ $pClass }}">{{ $pName }}</span>
                        @if ($job->social_author)<span class="text-ink/55">{{ $job->social_author }}</span>@endif
                        <span class="inline-flex items-center gap-1 {{ $fresh ? 'text-forest font-medium' : 'text-ink/45' }}"><x-icon name="clock" class="w-3.5 h-3.5" /> {{ $job->posted_at?->diffForHumans() ?? 'recently' }}</span>
                        @if ($fresh)<span class="rounded-full bg-forest-light text-forest-dark px-2 py-0.5 font-medium">Fresh</span>@endif
                        @if ($applied)<span class="rounded-full bg-forest text-white px-2 py-0.5 font-medium">Applied</span>@endif
                    </div>
                    <h2 class="mt-2 font-serif text-lg font-semibold leading-snug">
                        <a href="{{ $job->url }}" target="_blank" rel="noopener" class="hover:text-forest transition-colors">
                            @if ($job->isTranslated())<span x-show="!tr">{{ $job->title }}</span><span x-show="tr" x-cloak>{{ $job->title_translated }}</span>@else{{ $job->title }}@endif
                        </a>
                    </h2>
                    <p class="text-sm text-ink/60 mt-0.5 flex flex-wrap items-center gap-x-3">
                        @if ($job->company)<span class="inline-flex items-center gap-1"><x-icon name="building" class="w-3.5 h-3.5 text-ink/35" /> {{ $job->company }}</span>@endif
                        @if ($job->location || $job->is_remote)<span class="inline-flex items-center gap-1"><x-icon name="pin" class="w-3.5 h-3.5 text-ink/35" /> {{ $job->location ?: 'Remote' }}</span>@endif
                    </p>
                </div>
                <span class="shrink-0 rounded-full text-xs font-medium px-3 py-1 {{ $tierClass }}">{{ $tierLabel }}</span>
            </div>

            @include('partials.also-posted', ['job' => $job])

            {{-- How to apply: what the AI found, always checked against the post text --}}
            <div class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $emailable ? 'border-forest/30 bg-forest-50' : 'border-line bg-paper' }}">
                @if ($emailable)
                    <p class="font-medium text-forest-dark flex items-center gap-1.5"><x-icon name="mail" class="w-4 h-4" /> Apply by email: {{ $job->apply_email }}</p>
                    @if ($job->apply_subject)<p class="text-xs text-ink/60 mt-1">Subject asked for: <span class="font-medium">"{{ $job->apply_subject }}"</span></p>@endif
                @elseif ($job->needs_manual_input)
                    <p class="font-medium text-rust flex items-center gap-1.5"><x-icon name="alert" class="w-4 h-4" /> This post asks for more than a CV and cover letter. Apply yourself.</p>
                @elseif ($job->apply_url && $job->apply_url !== $job->url)
                    <p class="font-medium text-ink/80 flex items-center gap-1.5"><x-icon name="link" class="w-4 h-4" /> Apply through the link in the post</p>
                @else
                    <p class="font-medium text-ink/80 flex items-center gap-1.5"><x-icon name="bolt" class="w-4 h-4" /> Reply or message the poster on {{ $pName }}</p>
                @endif
                @if ($job->apply_instructions)<p class="text-xs text-ink/65 mt-1.5 leading-relaxed">Their words: {{ $job->apply_instructions }}</p>@endif
                @unless ($job->ai_extracted_at)<p class="text-xs text-ink/45 mt-1.5">Not analysed by the AI yet. Read the post to see how to apply.</p>@endunless
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                @foreach ($job->matched_keywords ?? [] as $kw)
                    @php $generic = in_array($kw, $job->match_generic ?? [], true); @endphp
                    <span class="rounded-full text-xs px-2.5 py-1 {{ $generic ? 'bg-line/70 text-ink/55' : 'bg-gold-light text-gold' }}">{{ $kw }}</span>
                @endforeach
                @if ($job->languageName())
                    <span class="rounded-full bg-gold-light text-gold text-xs px-2.5 py-1 inline-flex items-center gap-1"><x-icon name="globe" class="w-3 h-3" /> {{ $job->languageName() }}</span>
                    @if ($job->isTranslated())
                        <button type="button" x-on:click="tr = !tr" class="rounded-full border border-forest/30 text-forest hover:bg-forest hover:text-white text-xs font-medium px-2.5 py-1 transition-colors" x-text="tr ? 'Show original' : 'Show translation'"></button>
                    @elseif ($job->translation_status === 'pending')
                        <span class="rounded-full border border-line text-ink/50 text-xs px-2.5 py-1">Translation queued…</span>
                    @endif
                @endif
            </div>

            <div class="mt-3">
                <p class="text-sm text-ink/70 leading-relaxed whitespace-pre-line" x-bind:class="full ? '' : 'line-clamp-3'">
                    @if ($job->isTranslated() && $job->description_translated)<span x-show="!tr">{{ $job->description }}</span><span x-show="tr" x-cloak>{{ $job->description_translated }}</span>@else{{ $job->description }}@endif
                </p>
                <button type="button" x-on:click="full = !full" class="mt-1 text-xs font-medium text-forest hover:text-forest-dark" x-text="full ? 'Show less' : 'Read the full post'"></button>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 pt-4 mt-4 border-t border-line text-sm">
                <a href="{{ $job->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-4 py-1.5 transition-colors">
                    Open on {{ $pName }} <x-icon name="external" class="w-3.5 h-3.5" />
                </a>

                @if ($emailable && ! $applied)
                    <button type="button" x-on:click="confirmSend = true" class="inline-flex items-center gap-1.5 rounded-full border border-forest text-forest hover:bg-forest hover:text-white font-medium px-4 py-1.5 transition-colors"><x-icon name="bolt" class="w-4 h-4" /> Send application now</button>
                @endif

                @if ($job->apply_url && $job->apply_url !== $job->url)
                    <a href="{{ $job->apply_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-forest hover:text-forest-dark"><x-icon name="link" class="w-4 h-4" /> Apply link</a>
                @endif

                <a data-no-live href="{{ route('jobs.resume', $job) }}" target="_blank" class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-forest"><x-icon name="doc" class="w-4 h-4" /> Tailored resume</a>
                <a href="{{ route('jobs.coverLetter', $job) }}" class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-forest"><x-icon name="mail" class="w-4 h-4" /> Cover letter</a>

                @unless ($applied)
                    <form method="POST" action="{{ route('jobs.applied', $job) }}">@csrf <button type="submit" class="inline-flex items-center gap-1 font-medium text-forest hover:text-forest-dark"><x-icon name="check" class="w-4 h-4" /> Mark applied</button></form>
                @endunless
                <form method="POST" action="{{ route('jobs.dismiss', $job) }}" class="ml-auto" data-live-remove="article">@csrf <button type="submit" class="inline-flex items-center gap-1 text-ink/40 hover:text-rust"><x-icon name="x" class="w-4 h-4" /> Dismiss</button></form>
            </div>

            {{-- Confirm before emailing a stranger --}}
            @if ($emailable)
                <div x-show="confirmSend" x-cloak x-transition class="mt-4 rounded-xl border border-forest/30 bg-forest-50 px-4 py-4 text-sm">
                    <p class="font-medium">Send a tailored application to <span class="text-forest-dark">{{ $job->apply_email }}</span>?</p>
                    <p class="text-xs text-ink/60 mt-1">Your CV is tailored to this post and attached, with a cover letter drafted from your real experience{{ $job->apply_subject ? ", subject \"{$job->apply_subject}\"" : '' }}. It takes a few seconds.</p>
                    <form method="POST" action="{{ route('social.send', $job) }}" class="mt-3 flex gap-2" x-data="{ busy: false }" x-on:submit="busy = true">
                        @csrf
                        <button type="submit" x-bind:disabled="busy" class="rounded-full bg-forest hover:bg-forest-dark disabled:opacity-60 text-white text-sm font-medium px-5 py-2 transition-colors"><span x-show="!busy">Yes, send it</span><span x-show="busy" x-cloak>Preparing and sending…</span></button>
                        <button type="button" x-on:click="confirmSend = false" class="rounded-full border border-line bg-white px-5 py-2 text-sm">Cancel</button>
                    </form>
                </div>
            @endif
        </article>
    @empty
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <span class="mx-auto w-12 h-12 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="bolt" class="w-6 h-6" /></span>
            <p class="mt-4 font-medium">No matching posts in this window</p>
            <p class="text-sm text-ink/55 mt-1 max-w-md mx-auto">New posts are collected hourly from sources that allow it, and only ones matching your core skills show here. Try "Last 7 days", or check that your profile has required skills.</p>
        </div>
    @endforelse

    <div class="mt-6">{{ $posts->links() }}</div>
@endsection
