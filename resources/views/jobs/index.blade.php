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
        <div class="bg-white border border-line border-l-4 border-l-forest rounded-lg px-5 py-4 mb-4">
            <h2 class="font-serif text-base font-semibold">
                <a href="{{ $job->url }}" target="_blank" rel="noopener" class="hover:text-forest transition-colors">{{ $job->title }}</a>
            </h2>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink/60 mt-1 mb-3">
                <span>{{ $job->company ?? 'Unknown company' }}</span>
                <span class="text-ink/30">/</span>
                <span>{{ $job->location ?? 'N/A' }}</span>
                <span class="text-ink/30">/</span>
                <span>{{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}</span>
            </div>

            <div class="flex flex-wrap gap-1.5 mb-4">
                <span class="inline-flex items-center rounded-full bg-forest-light text-forest-dark text-xs px-2.5 py-1">{{ $job->source }}</span>
                @foreach ($job->matched_keywords ?? [] as $kw)
                    <span class="inline-flex items-center rounded-full bg-gold-light text-gold text-xs px-2.5 py-1">{{ $kw }}</span>
                @endforeach
            </div>

            <div class="flex gap-2">
                <form method="POST" action="{{ route('jobs.applied', $job) }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-forest hover:text-forest-dark transition-colors">Mark applied</button>
                </form>
                <span class="text-line">&#124;</span>
                <form method="POST" action="{{ route('jobs.dismiss', $job) }}">
                    @csrf
                    <button type="submit" class="text-sm text-ink/50 hover:text-rust transition-colors">Dismiss</button>
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
