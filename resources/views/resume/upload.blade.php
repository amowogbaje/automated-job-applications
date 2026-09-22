@extends('layouts.app')

@section('title', 'Resume')

@section('content')

    <div class="mb-8">
        <h1 class="font-serif text-2xl font-semibold text-ink">Your resume</h1>
        <p class="text-sm text-ink/60 mt-1">One active resume, compiled fresh and tailored for every application.</p>
    </div>

    @if ($resume)
        <div class="bg-white border border-line border-l-4 border-l-forest rounded-lg px-5 py-4 mb-6">
            <p class="text-sm text-ink/50 mb-1">Active resume</p>
            <p class="font-serif text-lg font-semibold">{{ $resume->full_name }}</p>
            <p class="text-sm text-ink/60 mb-3">{{ $resume->headline }}</p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink/60">
                <span>{{ $resume->experiences->count() }} {{ Str::plural('role', $resume->experiences->count()) }}</span>
                <span class="text-ink/30 hidden sm:inline">/</span>
                <span>{{ $resume->skills->count() }} {{ Str::plural('skill', $resume->skills->count()) }}</span>
                <span class="text-ink/30 hidden sm:inline">/</span>
                <span>{{ $resume->projects->count() }} {{ Str::plural('project', $resume->projects->count()) }}</span>
                <span class="text-ink/30 hidden sm:inline">/</span>
                <a href="{{ route('resume.download') }}" class="font-medium text-forest hover:text-forest-dark transition-colors">Download PDF</a>
            </div>
        </div>
    @elseif ($claimable)
        <div class="bg-white border border-line border-l-4 border-l-gold rounded-lg px-5 py-4 mb-6">
            <p class="text-sm text-ink/50 mb-1">Ready to claim</p>
            <p class="font-serif text-lg font-semibold">{{ $claimable->full_name }}</p>
            <p class="text-sm text-ink/60 mb-4">{{ $claimable->headline }}</p>
            <form method="POST" action="{{ route('resume.claim') }}">
                @csrf
                <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors">
                    Claim this resume
                </button>
            </form>
        </div>
    @endif

    <div class="bg-white border border-line rounded-lg px-5 py-5">
        <p class="text-sm text-ink/60 mb-5">Upload a PDF and, optionally, your personal site. The PDF is the only source of facts — the site only shapes tone and phrasing, never adds a skill or role that isn't in the PDF.</p>

        <form method="POST" action="{{ route('resume.upload') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="resume_pdf" class="block text-sm text-ink/70 mb-1.5">Resume PDF</label>
                <input id="resume_pdf" type="file" name="resume_pdf" accept="application/pdf"
                    class="w-full text-sm text-ink/70 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-forest-light file:text-forest-dark file:text-sm file:font-medium hover:file:bg-forest/20">
            </div>

            <div>
                <label for="website_url" class="block text-sm text-ink/70 mb-1.5">Personal website (optional)</label>
                <input id="website_url" type="text" name="website_url" placeholder="amowogbaje.com" value="{{ old('website_url') }}"
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2.5">
            </div>

            <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors">
                {{ $resume ? 'Replace resume' : 'Import resume' }}
            </button>
        </form>
    </div>

@endsection
