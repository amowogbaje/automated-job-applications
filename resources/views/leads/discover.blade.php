@extends('layouts.app')

@section('title', 'Find leads')

@section('content')

    <div class="mb-6">
        <h1 class="font-serif text-2xl font-semibold text-ink">Find leads</h1>
        <p class="text-sm text-ink/60 mt-1">Companies that might need a developer, pulled from Hunter's public company directory — not scraped, not guessed.</p>
    </div>

    @if (! $hunterConfigured)
        <div class="bg-gold-light border border-gold/20 rounded-lg px-4 py-3 text-sm text-ink/80 mb-6">
            No <code class="bg-white px-1.5 py-0.5 rounded text-xs">HUNTER_API_KEY</code> set yet. Get a free key at
            <a href="https://hunter.io" target="_blank" rel="noopener" class="font-medium text-forest hover:text-forest-dark">hunter.io</a>
            and add it to <code class="bg-white px-1.5 py-0.5 rounded text-xs">.env</code> — the form below will work once it's set.
        </div>
    @else
        <div class="bg-white border border-line rounded-lg px-5 py-4 mb-6">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-ink/70">Discover calls used this month</span>
                <span class="font-medium {{ $used >= $limit ? 'text-rust' : 'text-ink' }}">{{ $used }} / {{ $limit }}</span>
            </div>
            <div class="h-2 rounded-full bg-paper overflow-hidden">
                <div class="h-full {{ $used >= $limit ? 'bg-rust' : 'bg-forest' }}" style="width: {{ min(100, round($used / $limit * 100)) }}%"></div>
            </div>

            @if ($suggestedSweeps->isNotEmpty())
                <p class="text-xs text-ink/50 mt-3 mb-1.5">Suggested next — one broad call per region beats one call per stack:</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($suggestedSweeps as $sweep)
                        <button type="submit" form="sweep-{{ $loop->index }}" class="text-xs rounded-full border border-forest/30 bg-forest-light text-forest-dark px-3 py-1.5 hover:bg-forest/20">
                            {{ $sweep['label'] }}
                        </button>
                        <form id="sweep-{{ $loop->index }}" method="POST" action="{{ route('leads.discover') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="query" value="Small software agencies or product companies that could use freelance backend help">
                            @foreach ($sweep['regions'] as $r)<input type="hidden" name="regions[]" value="{{ $r }}">@endforeach
                            @foreach ($sweep['stacks'] as $s)<input type="hidden" name="stacks[]" value="{{ $s }}">@endforeach
                        </form>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-ink/50 mt-3">All three regional sweeps done this month — from here, narrow in on whichever turned up the most results below.</p>
            @endif
        </div>
    @endif

    <div class="bg-white border border-line rounded-lg px-5 py-5">
        <form method="POST" action="{{ route('leads.discover') }}" class="space-y-5">
            @csrf

            <div>
                <label for="query" class="block text-sm text-ink/70 mb-1.5">Describe who you're looking for</label>
                <textarea id="query" name="query" rows="2" placeholder="e.g. Small software agencies in Europe that look understaffed on backend engineering"
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm">{{ old('query') }}</textarea>
                <p class="text-xs text-ink/50 mt-1">Optional — plain language works. Hunter's assistant turns it into filters.</p>
            </div>

            <div>
                <p class="block text-sm text-ink/70 mb-2">Stack (adds targeted keywords to the search — pick several to cover more ground per call)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['nestjs' => 'NestJS', 'fastapi' => 'FastAPI', 'golang' => 'Go', 'nodejs' => 'Node.js', 'laravel' => 'Laravel'] as $value => $label)
                        <label class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-forest has-[:checked]:bg-forest-light has-[:checked]:text-forest-dark">
                            <input type="checkbox" name="stacks[]" value="{{ $value }}" class="rounded border-line text-forest focus:ring-forest">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="block text-sm text-ink/70 mb-2">Region (broad — covers many countries in one call)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (['AMER' => 'Americas', 'EMEA' => 'Europe / UK / Ireland / South Africa', 'APAC' => 'Asia-Pacific'] as $code => $label)
                        <label class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-forest has-[:checked]:bg-forest-light has-[:checked]:text-forest-dark">
                            <input type="checkbox" name="regions[]" value="{{ $code }}" class="rounded border-line text-forest focus:ring-forest">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <details>
                <summary class="text-sm text-ink/50 cursor-pointer hover:text-ink">Narrow to specific countries instead</summary>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach (['US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'IE' => 'Ireland', 'ZA' => 'South Africa', 'IN' => 'India', 'SG' => 'Singapore', 'NG' => 'Nigeria'] as $code => $label)
                        <label class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-sm cursor-pointer has-[:checked]:border-forest has-[:checked]:bg-forest-light has-[:checked]:text-forest-dark">
                            <input type="checkbox" name="countries[]" value="{{ $code }}" class="rounded border-line text-forest focus:ring-forest">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </details>

            <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors">
                Search
            </button>
        </form>
    </div>

    @if ($recentSearches->isNotEmpty())
        <div class="mt-6 bg-white border border-line rounded-lg px-5 py-4">
            <p class="text-sm font-medium text-ink/80 mb-3">Recent searches, so you don't repeat a combo</p>
            <div class="space-y-2 text-sm">
                @foreach ($recentSearches as $search)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line last:border-0 pb-2 last:pb-0">
                        <span class="text-ink/70">
                            {{ collect(array_merge($search->regions ?? [], $search->countries ?? []))->implode(', ') ?: 'no location filter' }}
                            @if (! empty($search->stacks))
                                — {{ collect($search->stacks)->implode(', ') }}
                            @endif
                        </span>
                        <span class="text-ink/40 text-xs">{{ $search->results_count }} results, {{ $search->new_leads_count }} new · {{ $search->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 text-sm text-ink/50">
        <a href="{{ route('leads.index') }}" class="text-forest hover:text-forest-dark font-medium">&larr; Back to your pipeline</a>
    </div>

    <div class="mt-8 bg-white border border-line rounded-lg px-5 py-4 text-sm text-ink/60">
        <p class="font-medium text-ink/80 mb-1">How this stays above board</p>
        <p>This only surfaces companies and public counts — no email address is ever pulled until you click "Reveal contact" on a specific lead, which costs a Hunter credit each time. Nothing here scrapes LinkedIn, Google Maps, or any site against its terms — it's all through Hunter's own opt-in-respecting database.</p>
    </div>

@endsection
