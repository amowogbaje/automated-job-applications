<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Job Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; background: #fafafa; }
        h1 { font-size: 1.4rem; }
        form.filters { display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        form.filters input, form.filters select { padding: .4rem; }
        .job { background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 1rem; margin-bottom: .75rem; }
        .job h2 { font-size: 1.05rem; margin: 0 0 .25rem; }
        .job .meta { color: #666; font-size: .85rem; margin-bottom: .5rem; }
        .job .actions a, .job .actions button { font-size: .8rem; margin-right: .5rem; }
        .badge { display: inline-block; background: #eef; color: #335; border-radius: 4px; padding: .1rem .4rem; font-size: .75rem; margin-right: .25rem; }
        .pagination { margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Job Feed ({{ $jobs->total() }} matches) &middot; <a href="{{ route('drafts.index') }}" style="font-size:.9rem">View drafts</a></h1>

    <form class="filters" method="GET">
        <input type="text" name="q" placeholder="Filter keyword..." value="{{ $keyword }}">
        <select name="window">
            <option value="24" @selected($window == '24')>Last 24 hours</option>
            <option value="72" @selected($window == '72')>Last 3 days</option>
            <option value="all" @selected($window == 'all')>All time</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    @forelse ($jobs as $job)
        <div class="job">
            <h2><a href="{{ $job->url }}" target="_blank" rel="noopener">{{ $job->title }}</a></h2>
            <div class="meta">
                {{ $job->company ?? 'Unknown company' }}
                &middot; {{ $job->location ?? 'N/A' }}
                &middot; {{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}
                &middot; <span class="badge">{{ $job->source }}</span>
                @foreach ($job->matched_keywords ?? [] as $kw)
                    <span class="badge">{{ $kw }}</span>
                @endforeach
            </div>
            <div class="actions">
                <form method="POST" action="{{ route('jobs.applied', $job) }}" style="display:inline">
                    @csrf
                    <button type="submit">Mark applied</button>
                </form>
                <form method="POST" action="{{ route('jobs.dismiss', $job) }}" style="display:inline">
                    @csrf
                    <button type="submit">Dismiss</button>
                </form>
            </div>
        </div>
    @empty
        <p>No matching jobs in this window yet. Try widening the time range.</p>
    @endforelse

    <div class="pagination">
        {{ $jobs->links() }}
    </div>
</body>
</html>
