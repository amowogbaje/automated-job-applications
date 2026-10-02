@extends('layouts.app')

@section('title', 'Applicants')
@section('container', 'max-w-5xl')

@section('content')
@php
    $statuses = \App\Models\JobApplication::STATUSES;
    $badge = ['new' => 'bg-forest text-white', 'reviewing' => 'bg-gold-light text-gold', 'shortlisted' => 'bg-forest-100 text-forest-dark', 'rejected' => 'bg-line text-ink/55', 'hired' => 'bg-ink text-white'];
    $q = fn (array $extra) => route('employer.applicants', array_filter(array_merge(['job' => $jobFilter, 'status' => $status === 'all' ? null : $status], $extra), fn ($v) => $v !== null && $v !== ''));
@endphp
    <div class="mb-6">
        <h1 class="font-serif text-3xl font-semibold">Applicants</h1>
        <p class="text-sm text-ink/60 mt-1">Everyone who applied through this site to your jobs.</p>
    </div>

    <div class="bg-white border border-line rounded-2xl shadow-card p-4 mb-6 space-y-3">
        <form method="GET" x-data class="flex flex-wrap items-center gap-3">
            <label class="text-xs font-medium text-ink/55">Job</label>
            <select name="job" class="input w-auto" x-on:change="$el.form.submit()">
                <option value="">All jobs</option>
                @foreach ($myJobs as $j)<option value="{{ $j->id }}" @selected($jobFilter === $j->id)>{{ $j->title }}{{ $j->status !== 'published' ? ' (' . $j->status . ')' : '' }}</option>@endforeach
            </select>
            @if ($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        </form>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ $q(['status' => null]) }}" class="rounded-full border px-3.5 py-1.5 {{ $status === 'all' ? 'border-forest bg-forest text-white' : 'border-line hover:border-forest/40' }}">All <span class="opacity-70">{{ $counts->sum() }}</span></a>
            @foreach ($statuses as $key => $label)
                <a href="{{ $q(['status' => $key]) }}" class="rounded-full border px-3.5 py-1.5 {{ $status === $key ? 'border-forest bg-forest text-white' : 'border-line hover:border-forest/40' }}">{{ $label }} <span class="opacity-70">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>
    </div>

    @forelse ($applications as $a)
        @php $skills = $a->resume?->skills?->pluck('name')->take(12) ?? collect(); @endphp
        <article class="bg-white border border-line rounded-2xl shadow-card px-6 py-5 mb-4" x-data="{ open: {{ $a->status === 'new' && $loop->first ? 'true' : 'false' }} }">
            <div class="flex items-start gap-4">
                <span class="w-11 h-11 rounded-full bg-forest text-white grid place-items-center text-sm font-semibold shrink-0">{{ collect(explode(' ', $a->applicant->name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">{{ $a->applicant->name }}</h2>
                        <span class="rounded-full text-xs font-medium px-2.5 py-0.5 {{ $badge[$a->status] ?? 'bg-line' }}">{{ $statuses[$a->status] }}</span>
                    </div>
                    <p class="text-sm text-ink/55">{{ $a->resume?->headline ?: 'Applied for ' . $a->job->title }} · {{ $a->job->title }} · {{ $a->applied_at->diffForHumans() }}</p>
                </div>
                <button type="button" x-on:click="open = !open" class="text-sm font-medium text-forest hover:text-forest-dark inline-flex items-center gap-1 shrink-0"><span x-text="open ? 'Hide' : 'Review'"></span><x-icon name="chevron-down" class="w-4 h-4 transition-transform" x-bind:class="open ? 'rotate-180' : ''" /></button>
            </div>

            <div x-show="open" x-cloak x-collapse class="mt-5 pt-5 border-t border-line grid md:grid-cols-[1fr_260px] gap-6">
                <div class="space-y-4 min-w-0">
                    @if ($skills->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">@foreach ($skills as $s)<span class="rounded-full bg-gold-light text-gold text-xs px-2.5 py-1">{{ $s }}</span>@endforeach</div>
                    @endif
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-ink/45 mb-1.5">Cover letter</p>
                        <p class="text-sm text-ink/75 leading-relaxed whitespace-pre-line">{{ $a->cover_letter }}</p>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm">
                        @if ($a->resume_pdf_path)
                            <a href="{{ route('employer.applications.resume', $a) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-forest hover:text-forest-dark"><x-icon name="doc" class="w-4 h-4" /> View resume</a>
                            <a href="{{ route('employer.applications.resume', ['application' => $a, 'download' => 1]) }}" class="text-ink/55 hover:text-forest">Download</a>
                        @endif
                        <a href="mailto:{{ $a->applicant->email }}?subject={{ rawurlencode('Re: ' . $a->job->title . ' at ' . auth()->user()->company_name) }}" class="inline-flex items-center gap-1.5 font-medium text-forest hover:text-forest-dark"><x-icon name="mail" class="w-4 h-4" /> Email {{ explode(' ', $a->applicant->name)[0] }}</a>
                    </div>
                </div>

                <form method="POST" action="{{ route('employer.applications.update', $a) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block text-xs font-medium text-ink/55 mb-1">Stage</label>
                        <select name="status" class="input">@foreach ($statuses as $key => $label)<option value="{{ $key }}" @selected($a->status === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/55 mb-1">Private note (only you see this)</label>
                        <textarea name="employer_note" rows="3" class="input">{{ $a->employer_note }}</textarea>
                    </div>
                    <button class="w-full rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-2 transition-colors">Save</button>
                </form>
            </div>
        </article>
    @empty
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <span class="mx-auto w-12 h-12 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="user" class="w-6 h-6" /></span>
            <p class="mt-4 font-medium">No applicants {{ $status !== 'all' || $jobFilter ? 'match these filters' : 'yet' }}</p>
            <p class="text-sm text-ink/55 mt-1">Applicants appear here as soon as someone applies, and you get an email each time.</p>
        </div>
    @endforelse
@endsection
