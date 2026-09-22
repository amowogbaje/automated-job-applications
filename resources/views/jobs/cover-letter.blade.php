@extends('layouts.app')

@section('title', 'Cover letter — ' . $job->company)

@section('content')

    <div class="mb-6">
        <a href="{{ route('jobs.index') }}" class="text-sm text-forest hover:text-forest-dark font-medium">&larr; Back to jobs</a>
    </div>

    <div class="mb-6">
        <h1 class="font-serif text-2xl font-semibold text-ink">{{ $job->title }}</h1>
        <p class="text-sm text-ink/60 mt-1">{{ $job->company }} — cover letter tailored to this listing's description</p>
    </div>

    <div class="bg-white border border-line rounded-lg px-6 py-6">
        <p id="letter-text" class="text-sm text-ink/80 whitespace-pre-line leading-relaxed">{{ $job->tailored_cover_letter }}</p>
    </div>

    <div class="flex flex-wrap gap-4 mt-4">
        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('letter-text').innerText); this.textContent='Copied'"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-forest hover:text-forest-dark transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>
            Copy text
        </button>
        <a href="{{ route('jobs.coverLetter', ['job' => $job, 'download' => 1]) }}" class="text-sm text-ink/60 hover:text-forest transition-colors">Download as .txt</a>
        <a href="{{ route('jobs.resume', $job) }}" target="_blank" class="text-sm text-ink/60 hover:text-forest transition-colors">View the matching tailored resume &rarr;</a>
    </div>

@endsection
