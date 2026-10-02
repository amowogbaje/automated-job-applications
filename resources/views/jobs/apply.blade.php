@extends('layouts.app')

@section('title', 'Apply to ' . $job->title)
@section('container', 'max-w-3xl')

@section('content')
    <a href="{{ route('jobs.public', $job) }}" class="text-sm text-ink/55 hover:text-forest inline-flex items-center gap-1 mb-4">&larr; Back to the job</a>
    <h1 class="font-serif text-3xl font-semibold">Apply to {{ $job->title }}</h1>
    <p class="text-ink/60 mt-1 mb-6">{{ $job->company }}@if ($job->location) · {{ $job->location }}@endif</p>

    <form method="POST" action="{{ route('jobs.apply.store', $job) }}" class="space-y-6"
        x-data="{ letter: @js(old('cover_letter', '')), tailor: true, busy: false, drafting: false, err: '',
            async draft() {
                this.drafting = true; this.err = '';
                try {
                    const r = await fetch('{{ route('jobs.apply.draftLetter', $job) }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                    const d = await r.json();
                    if (!r.ok) throw new Error(d.error || 'Could not draft a letter.');
                    this.letter = d.cover_letter;
                } catch (e) { this.err = e.message; }
                this.drafting = false;
            } }"
        x-on:submit="busy = true">
        @csrf

        <section class="bg-white border border-line rounded-2xl shadow-card p-6">
            <h2 class="font-semibold">Your resume</h2>
            <p class="text-sm text-ink/60 mt-1">{{ $resume->full_name }} · {{ $resume->headline ?: 'Active resume' }}</p>
            <label class="mt-4 flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="tailor" value="1" x-model="tailor" class="mt-1 rounded border-line text-forest focus:ring-forest">
                <span><span class="block text-sm font-medium">Tailor it to this job</span><span class="block text-xs text-ink/55">Reorders your real skills and projects to lead with what this role needs. Nothing is added or invented.</span></span>
            </label>
        </section>

        <section class="bg-white border border-line rounded-2xl shadow-card p-6">
            <div class="flex items-center justify-between gap-3 mb-2">
                <h2 class="font-semibold">Cover letter</h2>
                <button type="button" x-on:click="draft()" x-bind:disabled="drafting" class="inline-flex items-center gap-1.5 rounded-full border border-forest/30 text-forest hover:bg-forest hover:text-white text-sm font-medium px-4 py-1.5 transition-colors disabled:opacity-60">
                    <x-icon name="sparkle" class="w-4 h-4" /> <span x-text="drafting ? 'Drafting…' : 'Draft it for me'"></span>
                </button>
            </div>
            <textarea name="cover_letter" x-model="letter" rows="12" required minlength="40" maxlength="5000" class="input" placeholder="Say why you're a fit for this role. Be specific."></textarea>
            <p x-show="err" x-cloak x-text="err" class="text-sm text-rust mt-2"></p>
            <p class="text-xs text-ink/50 mt-2">The draft only uses what's on your resume. Read it and make it yours before you send.</p>
        </section>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('jobs.public', $job) }}" class="rounded-full border border-line px-5 py-2.5 text-sm">Cancel</a>
            <button type="submit" x-bind:disabled="busy || letter.trim().length < 40" class="rounded-full bg-forest hover:bg-forest-dark disabled:opacity-50 text-white text-sm font-medium px-7 py-2.5 transition-colors">
                <span x-show="!busy">Send application</span><span x-show="busy" x-cloak>Preparing your resume…</span>
            </button>
        </div>
    </form>
@endsection
