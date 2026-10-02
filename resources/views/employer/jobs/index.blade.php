@extends('layouts.app')

@section('title', 'My jobs')
@section('container', 'max-w-5xl')

@section('content')
@php $badge = ['published' => ['Live', 'bg-forest text-white'], 'draft' => ['Draft', 'bg-line text-ink/60'], 'closed' => ['Closed', 'bg-rust-light text-rust']]; @endphp
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div><h1 class="font-serif text-3xl font-semibold">My jobs</h1><p class="text-sm text-ink/60 mt-1">{{ $jobs->count() }} total</p></div>
        <a href="{{ route('employer.jobs.create') }}" class="inline-flex items-center gap-2 rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors"><x-icon name="plus" class="w-4 h-4" /> Post a job</a>
    </div>

    @forelse ($jobs as $job)
        @php [$label, $cls] = $badge[$job->status] ?? ['Live', 'bg-forest text-white']; $expired = $job->status === 'published' && $job->closes_at && $job->closes_at->isPast(); @endphp
        <article class="bg-white border border-line rounded-2xl shadow-card px-6 py-5 mb-4" x-data="{ confirmDelete: false }">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="font-serif text-lg font-semibold leading-snug"><a href="{{ $job->status === 'draft' ? route('employer.jobs.edit', $job) : route('jobs.public', $job) }}" class="hover:text-forest">{{ $job->title }}</a></h2>
                        <span class="rounded-full text-xs font-medium px-2.5 py-0.5 {{ $expired ? 'bg-rust-light text-rust' : $cls }}">{{ $expired ? 'Expired' : $label }}</span>
                    </div>
                    <p class="text-sm text-ink/55 mt-1">{{ $job->location ?: ($job->is_remote ? 'Remote' : 'No location') }} · {{ ucfirst(str_replace('_', ' ', $job->employment_type ?? '')) }}@if ($job->posted_at) · posted {{ $job->posted_at->diffForHumans() }}@endif @if ($job->status === 'published' && $job->closes_at && ! $expired) · closes {{ $job->closes_at->diffForHumans() }}@endif</p>
                    @if ($job->also_posted)<p class="text-xs text-ink/45 mt-1">{{ count($job->also_posted) }} {{ count($job->also_posted) === 1 ? 'copy' : 'copies' }} of this job elsewhere {{ count($job->also_posted) === 1 ? 'was' : 'were' }} merged here.</p>@endif
                </div>
                @if ($job->apply_method === 'native')
                    <a href="{{ route('employer.applicants', ['job' => $job->id]) }}" class="text-sm text-right hover:text-forest"><span class="block font-serif text-2xl font-semibold">{{ $job->applications_count }}</span><span class="text-xs text-ink/50">applicant{{ $job->applications_count === 1 ? '' : 's' }}@if ($job->new_applications_count) · {{ $job->new_applications_count }} new @endif</span></a>
                @else
                    <span class="text-xs text-ink/45 text-right">Applications go {{ $job->apply_method === 'email' ? 'to ' . $job->apply_email : 'to your site' }}</span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 pt-4 mt-4 border-t border-line text-sm">
                <a href="{{ route('employer.jobs.edit', $job) }}" class="font-medium text-forest hover:text-forest-dark inline-flex items-center gap-1"><x-icon name="pencil" class="w-4 h-4" /> Edit</a>
                @if ($job->status !== 'draft')<a href="{{ route('jobs.public', $job) }}" class="text-ink/65 hover:text-forest inline-flex items-center gap-1"><x-icon name="eye" class="w-4 h-4" /> View</a>@endif
                <form method="POST" action="{{ route('employer.jobs.duplicate', $job) }}">@csrf <button class="text-ink/65 hover:text-forest">Duplicate</button></form>
                @if ($job->status === 'published' && ! $expired)
                    <form method="POST" action="{{ route('employer.jobs.close', $job) }}" class="ml-auto">@csrf <button class="text-ink/50 hover:text-rust">Close job</button></form>
                @elseif ($job->status !== 'draft')
                    <form method="POST" action="{{ route('employer.jobs.reopen', $job) }}" class="ml-auto">@csrf <button class="font-medium text-forest hover:text-forest-dark">Reopen for {{ config('employer.default_duration') }} days</button></form>
                @endif
                @if ($job->applications_count === 0)
                    <button type="button" x-on:click="confirmDelete = true" class="text-ink/40 hover:text-rust {{ $job->status === 'draft' ? 'ml-auto' : '' }}">Delete</button>
                @endif
            </div>
            @if ($job->applications_count === 0)
                <div x-show="confirmDelete" x-cloak x-transition class="mt-3 flex items-center gap-3 rounded-lg bg-rust-light px-4 py-2.5 text-sm text-rust">
                    Delete “{{ $job->title }}” for good?
                    <form method="POST" action="{{ route('employer.jobs.destroy', $job) }}" class="ml-auto">@csrf @method('DELETE')<button class="font-medium underline">Yes, delete</button></form>
                    <button type="button" x-on:click="confirmDelete = false" class="text-ink/60">Cancel</button>
                </div>
            @endif
        </article>
    @empty
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <p class="font-medium">No jobs yet</p>
            <a href="{{ route('employer.jobs.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-full bg-forest text-white text-sm font-medium px-5 py-2.5">Post your first job</a>
        </div>
    @endforelse
@endsection
