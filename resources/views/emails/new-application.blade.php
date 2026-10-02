<!doctype html>
<html>
<body style="font-family: system-ui, sans-serif; line-height: 1.55; color: #222; max-width: 600px;">
    <h2 style="margin-bottom: .25rem;">{{ $application->applicant->name }} applied for {{ $application->job->title }}</h2>
    <p style="color: #666; margin-top: 0;">{{ $application->applicant->email }} &middot; {{ $application->applied_at->diffForHumans() }}</p>

    @if ($application->cover_letter)
        <div style="border-left: 4px solid #1F5D50; background: #f6f5f0; padding: .75rem 1rem; white-space: pre-line;">{{ $application->cover_letter }}</div>
    @endif

    <p style="margin-top: 1.25rem;">
        Their resume is attached.
        <a href="{{ route('employer.applicants', ['job' => $application->job_listing_id]) }}" style="color: #1a4fa0;">Review, shortlist or reply in your dashboard</a>.
        Replying to this email goes straight to the applicant.
    </p>
</body>
</html>
