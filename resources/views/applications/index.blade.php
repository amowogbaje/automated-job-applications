@extends('layouts.app')

@section('title', 'Applications')

@section('content')
@php
    $statuses = \App\Models\JobApplication::STATUSES;
    $badge = ['new' => ['Sent', 'bg-line text-ink/60'], 'reviewing' => ['Being reviewed', 'bg-gold-light text-gold'], 'shortlisted' => ['Shortlisted', 'bg-forest text-white'], 'rejected' => ['Not selected', 'bg-line text-ink/55'], 'hired' => ['Hired', 'bg-ink text-white']];
@endphp
    <div class="mb-6"><h1 class="font-serif text-3xl font-semibold">Your applications</h1><p class="text-sm text-ink/60 mt-1">Jobs you applied to through this site, with the employer's progress.</p></div>

    @forelse ($applications as $a)
        @php [$label, $cls] = $badge[$a->status] ?? ['Sent', 'bg-line']; @endphp
        <article class="bg-white border border-line rounded-2xl shadow-card px-6 py-5 mb-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-serif text-lg font-semibold leading-snug"><a href="{{ route('jobs.public', $a->job) }}" class="hover:text-forest">{{ $a->job->title }}</a></h2>
                <p class="text-sm text-ink/55 mt-0.5">{{ $a->job->company }}@if ($a->job->location) · {{ $a->job->location }}@endif · applied {{ $a->applied_at->diffForHumans() }}</p>
                @if ($a->job->status === 'closed')<p class="text-xs text-ink/45 mt-1">This job has since closed.</p>@endif
            </div>
            <span class="rounded-full text-xs font-medium px-3 py-1 {{ $cls }}">{{ $label }}</span>
        </article>
    @empty
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <span class="mx-auto w-12 h-12 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="check" class="w-6 h-6" /></span>
            <p class="mt-4 font-medium">No applications yet</p>
            <p class="text-sm text-ink/55 mt-1">Jobs marked "Apply here" in your feed are applied to right on this site.</p>
            <a href="{{ route('jobs.index') }}" class="mt-5 inline-flex rounded-full bg-forest text-white text-sm font-medium px-5 py-2.5">Browse jobs</a>
        </div>
    @endforelse

    <div class="mt-6">{{ $applications->links() }}</div>
@endsection
