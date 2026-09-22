@extends('layouts.app')

@section('title', 'Drafts')

@section('content')

    <div class="mb-8">
        <h1 class="font-serif text-2xl font-semibold text-ink">Application drafts</h1>
        <p class="text-sm text-ink/60 mt-1">Review before sending — nothing here goes out without you.</p>
    </div>

    @forelse ($drafts as $draft)
        <div class="bg-white border border-line rounded-lg px-5 py-5 mb-5">
            <h2 class="font-serif text-base font-semibold">
                <a href="{{ $draft->job->url }}" target="_blank" rel="noopener" class="hover:text-forest transition-colors">{{ $draft->job->title }}</a>
            </h2>
            <div class="flex items-center gap-3 text-sm text-ink/60 mt-1 mb-4">
                <span>{{ $draft->job->company }}</span>
                <span class="text-ink/30">/</span>
                <span>{{ $draft->job->source }}</span>
            </div>

            @if ($draft->tailored_summary)
                <div class="bg-gold-light border border-gold/20 rounded-lg px-4 py-3 text-sm text-ink/80 mb-3">
                    <span class="font-medium text-gold">Emphasis for this role —</span> {{ $draft->tailored_summary }}
                </div>
            @endif

            @if ($draft->resume_pdf_path)
                <div class="mb-4">
                    <a href="{{ route('drafts.resume', $draft) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-forest hover:text-forest-dark transition-colors">
                        Download the resume tailored for this job
                    </a>
                </div>
            @endif

            <form method="POST" action="{{ route('drafts.update', $draft) }}">
                @csrf
                @method('PATCH')
                <label for="cover_letter_{{ $draft->id }}" class="block text-xs text-ink/50 mb-1.5">Cover letter</label>
                <textarea id="cover_letter_{{ $draft->id }}" name="cover_letter" rows="7"
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm">{{ $draft->cover_letter }}</textarea>
                <button type="submit" class="mt-3 rounded-full bg-ink hover:bg-ink/80 text-white text-sm font-medium px-4 py-2 transition-colors">
                    Save edits
                </button>
            </form>

            <div class="flex gap-4 mt-3 pt-3 border-t border-line">
                <form method="POST" action="{{ route('drafts.sent', $draft) }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-forest hover:text-forest-dark transition-colors">I've sent this — mark applied</button>
                </form>
                <form method="POST" action="{{ route('drafts.discard', $draft) }}">
                    @csrf
                    <button type="submit" class="text-sm text-ink/50 hover:text-rust transition-colors">Discard</button>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white border border-line rounded-lg px-6 py-10 text-center">
            <p class="text-ink/60 text-sm">No drafts yet. Run <code class="bg-paper px-1.5 py-0.5 rounded text-xs">php artisan applications:generate</code> to create some from your top-matching jobs.</p>
        </div>
    @endforelse

    <div class="mt-6">
        {{ $drafts->links() }}
    </div>

@endsection
