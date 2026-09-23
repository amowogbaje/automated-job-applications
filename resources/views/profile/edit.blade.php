@extends('layouts.app')

@section('title', 'Career profile')

@section('content')

    <div class="mb-8">
        <h1 class="font-serif text-2xl font-semibold text-ink">Career profile</h1>
        <p class="text-sm text-ink/60 mt-1">This decides which jobs show up on your feed — it used to be one shared config for the whole install, now it's yours alone.</p>
    </div>

    @if ($resume)
        <form method="POST" action="{{ route('profile.fromResume') }}" class="mb-6">
            @csrf
            <div class="bg-white border border-line border-l-4 border-l-gold rounded-lg px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-ink/80">Populate from your resume</p>
                    <p class="text-sm text-ink/60 mt-0.5">Pulls all {{ $resume->skills->count() }} skills from "{{ $resume->full_name }}" into keywords and required skills below. Won't touch exclusions or the match threshold — save afterward to review first.</p>
                </div>
                <button type="submit" class="shrink-0 rounded-full bg-gold hover:brightness-95 text-white text-sm font-medium px-4 py-2 transition">
                    Populate from resume
                </button>
            </div>
        </form>
    @else
        <div class="bg-gold-light border border-gold/20 rounded-lg px-4 py-3 text-sm text-ink/80 mb-6">
            No active resume yet — <a href="{{ route('resume.upload') }}" class="font-medium text-forest hover:text-forest-dark">import or claim one</a> to auto-fill this from your actual skills, or just fill it in by hand below.
        </div>
    @endif

    <div class="bg-white border border-line rounded-lg px-5 py-5">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div>
                <label for="required_skills" class="block text-sm text-ink/70 mb-1.5">Required skills</label>
                <input type="text" id="required_skills" name="required_skills"
                    value="{{ old('required_skills', collect($profile->required_skills)->implode(', ')) }}"
                    placeholder="laravel, php"
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm">
                <p class="text-xs text-ink/50 mt-1">Comma-separated. A listing needs at least the number below matched from this list, or it's filtered out entirely. Leave empty to skip this gate (falls back to needing one keyword match instead).</p>
            </div>

            <div>
                <label for="min_required_matches" class="block text-sm text-ink/70 mb-1.5">Minimum required matches</label>
                <input type="number" id="min_required_matches" name="min_required_matches" min="1" max="20"
                    value="{{ old('min_required_matches', $profile->min_required_matches) }}"
                    class="w-28 rounded-md border-line focus:border-forest focus:ring-forest text-sm">
            </div>

            <div>
                <label for="keywords" class="block text-sm text-ink/70 mb-1.5">Nice-to-have keywords</label>
                <input type="text" id="keywords" name="keywords"
                    value="{{ old('keywords', collect($profile->keywords)->implode(', ')) }}"
                    placeholder="full-stack, vue, react"
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm">
                <p class="text-xs text-ink/50 mt-1">Comma-separated. Each match boosts a listing's score (and shows as a gold tag on the jobs feed) but isn't required.</p>
            </div>

            <div>
                <label for="excluded_keywords" class="block text-sm text-ink/70 mb-1.5">Excluded keywords</label>
                <input type="text" id="excluded_keywords" name="excluded_keywords"
                    value="{{ old('excluded_keywords', collect($profile->excluded_keywords)->implode(', ')) }}"
                    placeholder="wordpress, junior, unpaid, internship"
                    class="w-full rounded-md border-line focus:border-forest focus:ring-forest text-sm">
                <p class="text-xs text-ink/50 mt-1">Comma-separated. A single hit anywhere in the listing drops it, regardless of everything else.</p>
            </div>

            <button type="submit" class="rounded-full bg-forest hover:bg-forest-dark text-white text-sm font-medium px-5 py-2.5 transition-colors">
                Save profile
            </button>
        </form>
    </div>

    <div class="mt-6 bg-white border border-line rounded-lg px-5 py-4 text-sm text-ink/60">
        <p class="font-medium text-ink/80 mb-1">How this affects the feed</p>
        <p>New listings are fetched broadly (a light built-in filter just keeps out obviously non-tech postings) so nothing relevant to you gets discarded before you ever see it. Your profile above only affects what <em>your own</em> `/jobs` view shows and how it's scored — someone else's account with a different profile sees a differently filtered list from the same underlying data.</p>
    </div>

@endsection
