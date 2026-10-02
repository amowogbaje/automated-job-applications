@extends('layouts.app')

@section('title', 'Employer dashboard')
@section('container', 'max-w-6xl')

@section('content')
@php $user = auth()->user(); @endphp
    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <h1 class="font-serif text-3xl font-semibold">{{ $user->company_name ?: 'Your company' }}</h1>
            <p class="text-sm text-ink/60 mt-1">Hiring dashboard</p>
        </div>
        <a href="{{ route('employer.jobs.create') }}" class="inline-flex items-center gap-2 rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-6 py-3 transition-colors shadow-card"><x-icon name="plus" class="w-4 h-4" /> Post a job</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach ([['Live jobs', $stats['live'], 'briefcase', route('employer.jobs.index')], ['Applicants', $stats['applicants'], 'user', route('employer.applicants')], ['New to review', $stats['new'], 'bolt', route('employer.applicants', ['status' => 'new'])], ['Shortlisted', $stats['shortlisted'], 'check', route('employer.applicants', ['status' => 'shortlisted'])]] as [$label, $n, $icon, $href])
            <a href="{{ $href }}" class="bg-white border border-line rounded-2xl shadow-card p-5 hover:border-forest/30 transition-colors">
                <span class="w-10 h-10 rounded-lg bg-forest-50 text-forest grid place-items-center"><x-icon :name="$icon" class="w-5 h-5" /></span>
                <p class="mt-4 font-serif text-3xl font-semibold">{{ $n }}</p>
                <p class="text-sm text-ink/55">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    @if ($stats['live'] === 0 && $stats['drafts'] === 0)
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <span class="mx-auto w-12 h-12 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="briefcase" class="w-6 h-6" /></span>
            <p class="mt-4 font-serif text-xl font-semibold">Post your first job</p>
            <p class="text-sm text-ink/55 mt-1 max-w-md mx-auto">Three short steps. Candidates whose skills match see it straight away, ranked by fit.</p>
            <a href="{{ route('employer.jobs.create') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-6 py-3 transition-colors">Post a job <x-icon name="arrow-right" class="w-4 h-4" /></a>
        </div>
    @else
        <div class="grid lg:grid-cols-2 gap-6">
            <section class="bg-white border border-line rounded-2xl shadow-card p-6">
                <div class="flex items-center justify-between mb-4"><h2 class="font-serif text-lg font-semibold">Latest applicants</h2><a href="{{ route('employer.applicants') }}" class="text-sm text-forest font-medium">View all</a></div>
                @forelse ($recent as $a)
                    <a href="{{ route('employer.applicants', ['job' => $a->job_listing_id]) }}" class="flex items-center gap-3 py-3 border-t border-line first:border-t-0 hover:bg-forest-50/50 -mx-2 px-2 rounded-lg">
                        <span class="w-9 h-9 rounded-full bg-forest text-white grid place-items-center text-xs font-semibold shrink-0">{{ collect(explode(' ', $a->applicant->name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</span>
                        <span class="min-w-0 flex-1"><span class="block font-medium truncate">{{ $a->applicant->name }}</span><span class="block text-xs text-ink/50 truncate">{{ $a->job->title }} · {{ $a->applied_at->diffForHumans() }}</span></span>
                        <span class="text-xs rounded-full px-2.5 py-1 {{ $a->status === 'new' ? 'bg-forest text-white' : 'bg-line text-ink/60' }}">{{ \App\Models\JobApplication::STATUSES[$a->status] }}</span>
                    </a>
                @empty
                    <p class="text-sm text-ink/55">No applicants yet. They show up here the moment someone applies.</p>
                @endforelse
            </section>

            <section class="bg-white border border-line rounded-2xl shadow-card p-6">
                <div class="flex items-center justify-between mb-4"><h2 class="font-serif text-lg font-semibold">Live jobs</h2><a href="{{ route('employer.jobs.index') }}" class="text-sm text-forest font-medium">Manage</a></div>
                @forelse ($jobs as $j)
                    <div class="flex items-center gap-3 py-3 border-t border-line first:border-t-0">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('jobs.public', $j) }}" class="block font-medium truncate hover:text-forest">{{ $j->title }}</a>
                            <p class="text-xs text-ink/50">Posted {{ $j->posted_at?->diffForHumans() }}@if ($j->closes_at) · closes {{ $j->closes_at->diffForHumans() }}@endif</p>
                        </div>
                        <a href="{{ route('employer.applicants', ['job' => $j->id]) }}" class="text-sm text-ink/70 hover:text-forest whitespace-nowrap">{{ $j->applications_count }} applicant{{ $j->applications_count === 1 ? '' : 's' }}@if ($j->new_applications_count) <span class="ml-1 rounded-full bg-forest text-white text-[10px] px-1.5 py-0.5">{{ $j->new_applications_count }} new</span>@endif</a>
                    </div>
                @empty
                    <p class="text-sm text-ink/55">Nothing live right now.</p>
                @endforelse
            </section>
        </div>
    @endif
@endsection
