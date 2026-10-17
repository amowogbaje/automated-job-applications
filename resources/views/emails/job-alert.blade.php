<!doctype html>
<html>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1e293b; max-width: 640px;">
    <h2 style="margin-bottom: .25rem;">{{ $total }} new job{{ $total === 1 ? '' : 's' }} for {{ $skills ? implode(', ', $skills) : 'your skills' }}</h2>
    <p style="color: #64748b; font-size: .9rem; margin-top: 0;">Best matches first. Each job is only emailed once.</p>

    @foreach ($states as $state)
        @php $job = $state->job; @endphp
        <div style="border: 1px solid #e2e8f0; border-left: 4px solid {{ $state->tier === 'strong' ? '#4f46e5' : '#94a3b8' }}; border-radius: 8px; padding: .75rem 1rem; margin-bottom: .75rem;">
            <div style="font-weight: 600;">
                <a href="{{ $job->url }}" style="color: #4338ca; text-decoration: none;">{{ $job->isTranslated() ? $job->title_translated : $job->title }}</a>
            </div>
            <div style="color: #64748b; font-size: .85rem;">
                @if ($job->kind === 'social')
                    {{ $job->social_platform ? ucfirst($job->social_platform) : 'Social post' }}@if ($job->social_author) &middot; {{ $job->social_author }}@endif
                @else
                    {{ $job->company ?? 'Unknown company' }}
                @endif
                &middot; {{ $job->location ?? ($job->is_remote ? 'Remote' : 'N/A') }}
                &middot; {{ $job->posted_at?->diffForHumans() ?? 'recently' }}
                &middot; {{ $job->source }}
            </div>
            @if (! empty($state->matched))
                <div style="font-size: .8rem; color: #334155; margin-top: .25rem;">Matched: {{ implode(', ', $state->matched) }}</div>
            @endif
            @if ($job->kind === 'social' && $job->apply_instructions)
                <div style="font-size: .85rem; margin-top: .35rem;">{{ $job->apply_instructions }}</div>
            @endif
        </div>
    @endforeach

    @if ($total > $states->count())
        <p style="font-size: .9rem;">…and {{ $total - $states->count() }} more in <a href="{{ route('jobs.index') }}" style="color: #4338ca;">your feed</a>.</p>
    @else
        <p style="font-size: .9rem;"><a href="{{ route('jobs.index') }}" style="color: #4338ca;">Open your feed</a></p>
    @endif

    <p style="color: #94a3b8; font-size: .75rem; margin-top: 1.5rem;">
        You get this because you turned on job alerts. Change the skills or switch alerts off on
        <a href="{{ route('jobs.interests') }}" style="color: #94a3b8;">Job interests</a>.
    </p>
</body>
</html>
