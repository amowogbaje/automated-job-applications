@extends('layouts.app')

@section('title', $job->title . ' at ' . $job->company)
@section('container', 'max-w-4xl')

@section('content')
@php
    $user = auth()->user();
    $emp = $job->employer;
    $typeLabel = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship'][$job->employment_type] ?? null;
    $tierStyles = ['strong' => ['Strong match for you', 'bg-forest text-white'], 'good' => ['Good match for you', 'bg-forest-100 text-forest-dark'], 'weak' => ['Partial match', 'bg-line text-ink/60']];
@endphp

    @if ($isOwner && $job->status === 'draft')
        <div class="mb-5 rounded-xl bg-gold-light border border-gold/30 px-4 py-3 text-sm flex items-center justify-between gap-3"><span>This is a draft. Only you can see it.</span><a href="{{ route('employer.jobs.edit', $job) }}" class="font-medium text-forest underline">Edit and publish</a></div>
    @endif
    @if ($closed)
        <div class="mb-5 rounded-xl bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">This job is closed and no longer accepts applications.</div>
    @endif
    @if ($errors->has('apply'))
        <div class="mb-5 rounded-xl bg-rust-light border border-rust/20 text-rust px-4 py-3 text-sm">{{ $errors->first('apply') }}</div>
    @endif

    <div class="grid md:grid-cols-[1fr_280px] gap-6 items-start">
        <article class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-8">
            <div class="flex flex-wrap items-center gap-2 text-xs mb-3">
                @if ($typeLabel)<span class="rounded-full bg-forest-light text-forest-dark px-2.5 py-1">{{ $typeLabel }}</span>@endif
                @if ($job->is_remote)<span class="rounded-full bg-forest-light text-forest-dark px-2.5 py-1">Remote friendly</span>@endif
                @if ($job->salaryLabel())<span class="rounded-full bg-gold-light text-gold px-2.5 py-1">{{ $job->salaryLabel() }} / year</span>@endif
                @if ($match)<span class="rounded-full px-2.5 py-1 font-medium {{ $tierStyles[$match['tier']][1] }}">{{ $tierStyles[$match['tier']][0] }}</span>@endif
            </div>

            <h1 class="font-serif text-3xl font-semibold leading-tight">{{ $job->title }}</h1>
            <p class="mt-2 text-ink/65 flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="inline-flex items-center gap-1.5"><x-icon name="building" class="w-4 h-4 text-ink/40" /> {{ $job->company }}</span>
                @if ($job->location)<span class="inline-flex items-center gap-1.5"><x-icon name="pin" class="w-4 h-4 text-ink/40" /> {{ $job->location }}</span>@endif
                @if ($job->posted_at)<span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="w-4 h-4 text-ink/40" /> Posted {{ $job->posted_at->diffForHumans() }}</span>@endif
            </p>

            @if ($job->skills)
                <div class="mt-5 flex flex-wrap gap-1.5">
                    @foreach ($job->skills as $s)
                        @php $has = $match && in_array($s, $match['matched'], true); @endphp
                        <span class="rounded-full text-xs px-2.5 py-1 {{ $has ? 'bg-gold text-white font-medium' : 'bg-gold-light text-gold' }}" @if ($has) title="This is on your profile" @endif>{{ $s }}</span>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 pt-6 border-t border-line text-ink/80 leading-relaxed whitespace-pre-line">{{ $job->description }}</div>

            @if ($emp && ($emp->company_description || $emp->company_website))
                <div class="mt-8 pt-6 border-t border-line">
                    <h2 class="font-serif text-lg font-semibold">About {{ $job->company }}</h2>
                    @if ($emp->company_description)<p class="mt-2 text-sm text-ink/70 leading-relaxed whitespace-pre-line">{{ $emp->company_description }}</p>@endif
                    <p class="mt-2 text-sm text-ink/55">@if ($emp->company_size){{ $emp->company_size }} people · @endif @if ($emp->company_website)<a href="{{ $emp->company_website }}" target="_blank" rel="noopener nofollow" class="text-forest font-medium">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($emp->company_website, '/')) }}</a>@endif</p>
                </div>
            @endif

            @include('partials.also-posted', ['job' => $job])
        </article>

        <aside class="bg-white border border-line rounded-2xl shadow-card p-6 md:sticky md:top-24">
            @if ($isOwner)
                <p class="text-sm text-ink/60">This is your job.</p>
                @if ($applicantCount !== null)<p class="mt-1 font-serif text-3xl font-semibold">{{ $applicantCount }} <span class="text-sm font-sans font-normal text-ink/50">applicant{{ $applicantCount === 1 ? '' : 's' }}</span></p>@endif
                <a href="{{ route('employer.applicants', ['job' => $job->id]) }}" class="mt-4 block text-center rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-2.5 transition-colors">View applicants</a>
                <a href="{{ route('employer.jobs.edit', $job) }}" class="mt-2 block text-center rounded-full border border-line text-sm py-2.5 hover:border-forest/40">Edit job</a>
            @elseif ($closed)
                <p class="text-sm text-ink/60">Applications are closed.</p>
                @auth @if ($user->isSeeker())<a href="{{ route('jobs.index') }}" class="mt-4 block text-center rounded-full bg-forest text-white text-sm font-medium py-2.5">See similar jobs</a>@endif @endauth
            @elseif ($application)
                <p class="text-sm font-medium text-forest-dark flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> You applied {{ $application->applied_at->diffForHumans() }}</p>
                <p class="text-xs text-ink/55 mt-1">Status: {{ \App\Models\JobApplication::STATUSES[$application->status] }}</p>
                <a href="{{ route('applications.index') }}" class="mt-4 block text-center rounded-full border border-line text-sm py-2.5 hover:border-forest/40">Your applications</a>
            @else
                @php $target = $job->apply_method === 'native' ? route('jobs.apply.create', $job) : ($job->apply_method === 'email' ? 'mailto:' . $job->apply_email . '?subject=' . rawurlencode('Application: ' . $job->title) : $job->apply_url); @endphp
                @guest
                    <p class="text-sm text-ink/65">Create a free account to apply with a resume tailored to this job.</p>
                    <a href="{{ route('register') }}" class="mt-4 block text-center rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-3 transition-colors">Sign up to apply</a>
                    <a href="{{ route('login') }}" class="mt-2 block text-center text-sm text-ink/60 hover:text-forest">I already have an account</a>
                @else
                    @if ($user->isSeeker())
                        <a href="{{ $target }}" @if ($job->apply_method === 'web') target="_blank" rel="noopener nofollow" @endif class="block text-center rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium py-3 transition-colors">
                            {{ $job->apply_method === 'native' ? 'Apply now' : ($job->apply_method === 'email' ? 'Apply by email' : 'Apply on company site') }}
                        </a>
                        <p class="text-xs text-ink/50 mt-3 leading-relaxed">{{ $job->apply_method === 'native' ? 'Your resume is tailored to this role and sent with a cover letter. Takes about a minute.' : 'This opens the employer\'s own application.' }}</p>
                    @else
                        <p class="text-sm text-ink/60">You're signed in as an employer. Use a job seeker account to apply.</p>
                    @endif
                @endguest
            @endif
            <p class="mt-5 pt-4 border-t border-line text-xs text-ink/45">Found something wrong with this listing? Reply to the employer, or ignore it. We don't charge anyone to apply.</p>
        </aside>
    </div>
@endsection
