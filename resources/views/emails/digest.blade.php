<!doctype html>
<html>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #222; max-width: 640px;">

@if ($social->isNotEmpty())
    <h2 style="margin-bottom: .25rem;">{{ $social->count() }} fresh social post{{ $social->count() === 1 ? '' : 's' }} matching your skills</h2>
    <p style="color: #666; font-size: .9rem; margin-top: 0;">
        Newest first. Open the post and apply while it's fresh. Posts that said "email us" have a tailored application drafted
        (or already sent, if you turned on social auto-email).
    </p>

    @foreach ($social as $job)
        <div style="border: 1px solid #e2e2e2; border-left: 4px solid #1F5D50; border-radius: 8px; padding: .75rem 1rem; margin-bottom: .75rem;">
            <div style="font-weight: 600;">
                <a href="{{ $job->url }}" style="color: #1a4fa0; text-decoration: none;">{{ $job->title }}</a>
            </div>
            <div style="color: #666; font-size: .85rem;">
                {{ $job->social_platform === 'x' ? 'X' : ucfirst($job->social_platform ?? $job->source) }}
                @if ($job->social_author) &middot; {{ $job->social_author }} @endif
                &middot; {{ $job->posted_at?->diffForHumans() ?? 'recently' }}
                @if ($job->location) &middot; {{ $job->location }} @endif
            </div>
            @if ($job->apply_instructions)
                <div style="font-size: .85rem; margin-top: .35rem;">{{ $job->apply_instructions }}</div>
            @endif
            <div style="font-size: .85rem; margin-top: .35rem;">
                @if ($job->apply_method === 'email' && $job->apply_email && ! $job->needs_manual_input)
                    Apply by email: <strong>{{ $job->apply_email }}</strong>@if ($job->apply_subject) (subject: "{{ $job->apply_subject }}")@endif
                @elseif ($job->needs_manual_input)
                    This post asks for more than a CV and cover letter, so apply yourself.
                @endif
                @if ($job->apply_url && $job->apply_url !== $job->url)
                    &middot; <a href="{{ $job->apply_url }}" style="color: #1a4fa0;">Apply link</a>
                @endif
            </div>
            @if (! empty($job->matched_keywords))
                <div style="font-size: .8rem; color: #335; margin-top: .25rem;">Matched: {{ implode(', ', $job->matched_keywords) }}</div>
            @endif
        </div>
    @endforeach
@endif

@if ($jobs->isNotEmpty())
    <h2 style="margin-bottom: .25rem; {{ $social->isNotEmpty() ? 'margin-top: 1.5rem;' : '' }}">{{ $jobs->count() }} new job{{ $jobs->count() === 1 ? '' : 's' }} that need a manual application</h2>
    <p style="color: #666; font-size: .9rem; margin-top: 0;">
        These require filling a form on the employer's site or a job board, so they weren't auto-applied.
        Each one won't appear in future digests once sent here.
    </p>

    @foreach ($jobs as $job)
        <div style="border: 1px solid #e2e2e2; border-radius: 8px; padding: .75rem 1rem; margin-bottom: .75rem;">
            <div style="font-weight: 600;">
                <a href="{{ $job->url }}" style="color: #1a4fa0; text-decoration: none;">{{ $job->isTranslated() ? $job->title_translated : $job->title }}</a>
            </div>
            <div style="color: #666; font-size: .85rem;">
                {{ $job->company ?? 'Unknown company' }}
                &middot; {{ $job->location ?? 'N/A' }}
                &middot; {{ $job->posted_at?->diffForHumans() ?? 'unknown date' }}
                &middot; {{ $job->source }}
                @if ($job->isTranslated()) &middot; translated from {{ $job->languageName() }} @endif
            </div>
            @if (! empty($job->matched_keywords))
                <div style="font-size: .8rem; color: #335; margin-top: .25rem;">
                    Matched: {{ implode(', ', $job->matched_keywords) }}
                </div>
            @endif
        </div>
    @endforeach
@endif
</body>
</html>
