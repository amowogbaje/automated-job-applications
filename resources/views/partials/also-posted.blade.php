@php
    $labels = ['x' => 'X', 'hackernews' => 'Hacker News', 'mastodon' => 'Mastodon', 'bluesky' => 'Bluesky', 'lemmy' => 'Lemmy', 'reddit' => 'Reddit', 'github' => 'GitHub', 'native' => 'this site'];
    $others = collect($job->also_posted ?? [])->filter(fn ($o) => ! empty($o['url']))->unique('url')->take(6);
@endphp
@if ($others->isNotEmpty())
    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-ink/55">
        <span class="inline-flex items-center gap-1"><x-icon name="link" class="w-3.5 h-3.5" /> Also posted on:</span>
        @foreach ($others as $o)
            <a href="{{ $o['url'] }}" target="_blank" rel="noopener" class="rounded-full border border-line px-2.5 py-0.5 hover:border-forest/40 hover:text-forest transition-colors">{{ $labels[$o['platform'] ?? $o['source']] ?? ucfirst($o['platform'] ?? $o['source']) }}</a>
        @endforeach
    </div>
@endif
