@extends('layouts.app')

@section('title', 'Drafts')

@section('content')
@php
    $badge = [
        'draft' => ['Draft', 'bg-line text-ink/60'],
        'ready' => ['Ready to send', 'bg-forest-100 text-forest-dark'],
        'needs_input' => ['Needs your answer', 'bg-rust-light text-rust'],
        'pending' => ['Submitting…', 'bg-gold-light text-gold'],
        'sent' => ['Sent', 'bg-forest text-white'],
    ];
@endphp
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold">Application drafts</h1>
        <p class="text-sm text-ink/60 mt-1">Review before sending. Nothing here goes out without you, unless you've turned on auto-apply.</p>
    </div>

    @forelse ($drafts as $draft)
        @php [$label, $cls] = $badge[$draft->status] ?? [ucfirst($draft->status), 'bg-line text-ink/60']; @endphp
        <article class="bg-white border border-line rounded-2xl shadow-card px-6 py-5 mb-5" x-data="{ edit: false }">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-serif text-lg font-semibold leading-snug">
                        <a href="{{ $draft->job->url }}" target="_blank" rel="noopener" class="hover:text-forest transition-colors">{{ $draft->job->title }}</a>
                    </h2>
                    <p class="text-sm text-ink/60 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1"><x-icon name="building" class="w-3.5 h-3.5 text-ink/35" /> {{ $draft->job->company }}</span>
                        <span>{{ $draft->job->source }}</span>
                        @if ($draft->apply_channel)<span class="rounded-full border border-line px-2 py-0.5 text-xs">{{ str_replace('_', ' ', $draft->apply_channel) }}</span>@endif
                    </p>
                </div>
                <span class="shrink-0 rounded-full text-xs font-medium px-3 py-1 {{ $cls }}">{{ $label }}</span>
            </div>

            @if ($draft->status === 'needs_input' && $draft->pending_questions)
                <div class="mt-4 rounded-xl bg-rust-light border border-rust/20 px-4 py-3 text-sm text-ink/80">
                    <p class="font-medium text-rust">The form asked something I won't guess:</p>
                    <ul class="list-disc ml-5 mt-1">
                        @foreach ($draft->pending_questions as $q)<li>{{ is_array($q) ? json_encode($q) : $q }}</li>@endforeach
                    </ul>
                    <p class="mt-2">Add it under <a href="{{ route('profile.edit', ['tab' => 'autoapply']) }}" class="text-forest font-medium underline">Screening answers</a>, or apply yourself on <a href="{{ $draft->job->apply_url ?: $draft->job->url }}" target="_blank" rel="noopener" class="text-forest font-medium underline">the apply page</a>.</p>
                </div>
            @endif

            @if ($draft->error)
                <div class="mt-4 flex items-start gap-2 rounded-xl bg-rust-light border border-rust/20 px-4 py-3 text-sm text-ink/80">
                    <x-icon name="alert" class="w-4 h-4 mt-0.5 text-rust shrink-0" /> <span><strong class="text-rust">Auto-submit didn't go through:</strong> {{ $draft->error }}</span>
                </div>
            @endif

            @if ($draft->tailored_summary)
                <div class="mt-4 bg-gold-light border border-gold/20 rounded-xl px-4 py-3 text-sm text-ink/80">
                    <span class="font-medium text-gold">Emphasis for this role:</span> {{ $draft->tailored_summary }}
                </div>
            @endif

            @if ($draft->resume_pdf_path)
                <div class="mt-4 flex items-center gap-4 text-sm">
                    <a href="{{ route('drafts.resume', $draft) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-forest hover:text-forest-dark"><x-icon name="doc" class="w-4 h-4" /> Resume tailored for this job</a>
                    <a href="{{ route('drafts.resume', ['draft' => $draft, 'download' => 1]) }}" class="text-ink/50 hover:text-forest">Download</a>
                </div>
            @endif

            <div class="mt-4">
                <button type="button" x-on:click="edit = !edit" class="text-sm font-medium text-ink/65 hover:text-ink inline-flex items-center gap-1">
                    <span x-text="edit ? 'Hide cover letter' : 'Edit cover letter'"></span>
                    <x-icon name="chevron-down" class="w-4 h-4 transition-transform" x-bind:class="edit ? 'rotate-180' : ''" />
                </button>
                <p x-show="!edit" class="mt-2 text-sm text-ink/60 line-clamp-3 whitespace-pre-line">{{ $draft->cover_letter }}</p>
                <form x-show="edit" x-cloak x-collapse method="POST" action="{{ route('drafts.update', $draft) }}" class="mt-3">
                    @csrf @method('PATCH')
                    <textarea name="cover_letter" rows="8" class="input">{{ $draft->cover_letter }}</textarea>
                    <button type="submit" class="mt-3 rounded-full bg-ink hover:bg-ink/85 text-white text-sm font-medium px-5 py-2 transition-colors">Save edits</button>
                </form>
            </div>

            <div class="flex flex-wrap gap-5 mt-4 pt-4 border-t border-line text-sm">
                <a href="{{ $draft->job->apply_url ?: $draft->job->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-forest hover:bg-forest-dark text-white font-medium px-4 py-1.5 transition-colors">Open apply page <x-icon name="external" class="w-3.5 h-3.5" /></a>
                <form method="POST" action="{{ route('drafts.sent', $draft) }}">@csrf <button type="submit" class="font-medium text-forest hover:text-forest-dark inline-flex items-center gap-1"><x-icon name="check" class="w-4 h-4" /> I've sent this</button></form>
                <form method="POST" action="{{ route('drafts.discard', $draft) }}" class="ml-auto">@csrf <button type="submit" class="text-ink/45 hover:text-rust">Discard</button></form>
            </div>
        </article>
    @empty
        <div class="bg-white border border-line rounded-2xl px-6 py-14 text-center">
            <span class="mx-auto w-12 h-12 rounded-full bg-forest-50 text-forest grid place-items-center"><x-icon name="doc" class="w-6 h-6" /></span>
            <p class="mt-4 font-medium">No drafts yet</p>
            <p class="text-sm text-ink/55 mt-1">Strong matches get a tailored resume and cover letter drafted here automatically.</p>
        </div>
    @endforelse

    <div class="mt-6">{{ $drafts->links() }}</div>
@endsection
