<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Application Drafts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        .draft { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .draft h2 { font-size: 1.05rem; margin: 0 0 .25rem; }
        .draft .meta { color: #666; font-size: .85rem; margin-bottom: .5rem; }
        .draft textarea { width: 100%; min-height: 160px; font-family: inherit; padding: .5rem; box-sizing: border-box; }
        .draft .tailored { background: #fff9e6; border: 1px solid #f0e0a0; padding: .5rem .75rem; border-radius: 6px; font-size: .85rem; margin-bottom: .5rem; }
        .draft .actions { margin-top: .5rem; display: flex; gap: .5rem; }
        .status { padding: .5rem; background: #e6ffe6; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <h1>Application Drafts — review before sending</h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @forelse ($drafts as $draft)
        <div class="draft">
            <h2><a href="{{ $draft->job->url }}" target="_blank" rel="noopener">{{ $draft->job->title }}</a></h2>
            <div class="meta">{{ $draft->job->company }} &middot; {{ $draft->job->source }}</div>

            @if ($draft->tailored_summary)
                <div class="tailored"><strong>Emphasis for this role:</strong> {{ $draft->tailored_summary }}</div>
            @endif

            <form method="POST" action="{{ route('drafts.update', $draft) }}">
                @csrf
                @method('PATCH')
                <textarea name="cover_letter">{{ $draft->cover_letter }}</textarea>
                <div class="actions">
                    <button type="submit">Save edits</button>
                </div>
            </form>

            <div class="actions">
                <form method="POST" action="{{ route('drafts.sent', $draft) }}">
                    @csrf
                    <button type="submit">I've sent this — mark applied</button>
                </form>
                <form method="POST" action="{{ route('drafts.discard', $draft) }}">
                    @csrf
                    <button type="submit">Discard</button>
                </form>
            </div>
        </div>
    @empty
        <p>No drafts yet. Run <code>php artisan applications:generate</code> to create some from your top-matching jobs.</p>
    @endforelse

    {{ $drafts->links() }}
</body>
</html>
