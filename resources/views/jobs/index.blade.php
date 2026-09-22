@extends('layouts.app')

@section('title', 'Jobs')

@section('content')

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <h1 class="font-serif text-2xl font-semibold text-ink">Job feed</h1>
            <p class="text-sm text-ink/60 mt-1">{{ $jobs->total() }} matching {{ Str::plural('listing', $jobs->total()) }}</p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-2 w-full sm:w-auto">
            <div class="flex-1 sm:flex-none min-w-[140px]">
                <label for="q" class="block text-xs text-ink/50 mb-1">Keyword</label>
                <input type="text" id="q" name="q" placeholder="e.g. Laravel"
                    value="{{ $keyword }}"
                    class="w-full sm:w-44 rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2">
            </div>
            <div class="flex-1 sm:flex-none min-w-[140px]">
                <label for="window" class="block text-xs text-ink/50 mb-1">Window</label>
                <select id="window" name="window" class="w-full sm:w-auto rounded-md border-line focus:border-forest focus:ring-forest text-sm py-2">
                    <option value="24" @selected($window == '24')>Last 24 hours</option>
                    <option value="72" @selected($window == '72')>Last 3 days</option>
                    <option value="all" @selected($window == 'all')>All time</option>
                </select>
            </div>
            <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-4 py-2 transition-colors">
                Filter
            </button>
        </form>
    </div>

    @forelse ($jobs as $job)
        <div class="group bg-white border border-line border-l-4 border-l-forest rounded-lg px-5 py-4 mb-4 transition-shadow hover:shadow-[0_2px_12px_rgba(28,35,33,0.06)]">
            <h2 class="font-serif text-base font-semibold">
                <a href="{{ $job->url }}" target="_blank" rel="noopener" class="hover:text-forest transition-colors inline-flex items-center gap-1.5">
                    {{ $job->title }}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 opacity-0 group-hover:opacity-50 transition-opacity"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18a1 1 0 0 1 1 1v4.5M18 6 9 15M9 6H6a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-3"/></svg>
                </a>
            </h2>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink/60 mt-1 mb-3">
                <span class="inline-flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-ink/30"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7a1 1 0 0 1 1-1h5V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2h5a1 1 0 0 1 1 1v14M3 21h18M9 21v-4h6v4M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/></svg>
                    {{ $job->company ?? 'Unknown company' }}
                </span>
                <span>{{ $job->location ?? 'N/A' }}</span>
                <span class="inline-flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-ink/30"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/></svg>
                    {{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}
                </span>
            </div>

            <div class="flex flex-wrap gap-1.5 mb-3">
                <span class="inline-flex items-center rounded-full bg-forest-light text-forest-dark text-xs px-2.5 py-1">{{ $job->source }}</span>
                @foreach ($job->matched_keywords ?? [] as $kw)
                    <span class="inline-flex items-center rounded-full bg-gold-light text-gold text-xs px-2.5 py-1">{{ $kw }}</span>
                @endforeach
            </div>

            @if ($job->description)
                <details class="mb-3">
                    <summary class="text-sm text-ink/50 cursor-pointer hover:text-ink select-none">Job description</summary>
                    <p class="text-sm text-ink/70 mt-2 whitespace-pre-line leading-relaxed">{{ Str::limit($job->description, 1200) }}</p>
                </details>
            @endif

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-3 border-t border-line">
                <form method="POST" action="{{ route('jobs.applied', $job) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 text-sm font-medium text-forest hover:text-forest-dark transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        Mark applied
                    </button>
                </form>

                @if ($job->description)
                    <a href="{{ route('jobs.resume', $job) }}" target="_blank"
                        class="inline-flex items-center gap-1 text-sm font-medium text-ink/70 hover:text-forest transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15V3m0 12-4-4m4 4 4-4M3 17v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/></svg>
                        {{ $job->tailored_resume_path ? 'View tailored resume' : 'Generate tailored resume' }}
                    </a>
                    @if ($job->tailored_resume_path)
                        <a href="{{ route('jobs.resume', ['job' => $job, 'download' => 1]) }}"
                            class="text-sm text-ink/50 hover:text-forest transition-colors">Download</a>
                    @endif
                @endif

                <form method="POST" action="{{ route('jobs.dismiss', $job) }}" class="ml-auto">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 text-sm text-ink/40 hover:text-rust transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        Dismiss
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white border border-line rounded-lg px-6 py-10 text-center">
            <p class="text-ink/60 text-sm">No matching jobs in this window yet. Try widening the time range above.</p>
        </div>
    @endforelse

    <div class="mt-6">
        {{ $jobs->links() }}
    </div>

@endsection
