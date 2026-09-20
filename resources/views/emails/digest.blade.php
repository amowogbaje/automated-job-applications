<!doctype html>
<html>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #222; max-width: 640px;">
    <h2 style="margin-bottom: .25rem;">{{ $jobs->count() }} new job{{ $jobs->count() === 1 ? '' : 's' }} that need a manual application</h2>
    <p style="color: #666; font-size: .9rem; margin-top: 0;">
        These require filling a form on the employer's site or a job board, so they weren't auto-applied.
        Each one won't appear in future digests once sent here.
    </p>

    @foreach ($jobs as $job)
        <div style="border: 1px solid #e2e2e2; border-radius: 8px; padding: .75rem 1rem; margin-bottom: .75rem;">
            <div style="font-weight: 600;">
                <a href="{{ $job->url }}" style="color: #1a4fa0; text-decoration: none;">{{ $job->title }}</a>
            </div>
            <div style="color: #666; font-size: .85rem;">
                {{ $job->company ?? 'Unknown company' }}
                &middot; {{ $job->location ?? 'N/A' }}
                &middot; {{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}
                &middot; {{ $job->source }}
            </div>
            @if (! empty($job->matched_keywords))
                <div style="font-size: .8rem; color: #335; margin-top: .25rem;">
                    Matched: {{ implode(', ', $job->matched_keywords) }}
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
