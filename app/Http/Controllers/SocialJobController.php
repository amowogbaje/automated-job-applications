<?php

namespace App\Http\Controllers;

use App\Mail\JobApplicationMail;
use App\Models\ApplicationDraft;
use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\JobListingUserState;
use App\Models\Resume;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;

class SocialJobController extends Controller
{
    private const MAX_CANDIDATES_SCORED = 400;

    // GET /social — recent job posts from social platforms that match YOUR skills.
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $profile = CareerProfile::forUser($userId);

        $window = $request->query('window', '24');       // hours | all
        $platform = $request->query('platform', 'all');  // all|x|mastodon|hackernews
        $how = $request->query('how', 'all');            // all|email|link
        $sort = $request->query('sort', 'recent');       // recent|match
        $keyword = $request->query('q');
        $showApplied = $request->boolean('applied');

        $candidates = JobListing::query()
            ->social()
            ->with(['states' => fn ($q) => $q->where('user_id', $userId)])
            ->notDismissedBy($userId)
            ->when(! $showApplied, fn ($q) => $q->notAppliedBy($userId))
            ->when($window !== 'all', fn ($q) => $q->where('posted_at', '>=', now()->subHours((int) $window)))
            ->when($platform !== 'all', fn ($q) => $q->where('social_platform', $platform))
            ->when($how === 'email', fn ($q) => $q->where('apply_method', 'email'))
            ->when($how === 'link', fn ($q) => $q->where('apply_method', 'web'))
            ->matching($keyword)
            ->orderByDesc('posted_at')
            ->limit(self::MAX_CANDIDATES_SCORED)
            ->get();

        $scored = $candidates
            ->map(function (JobListing $job) use ($profile) {
                $r = $profile->score($job->scoringTitle(), $job->scoringDescription());
                $job->match_score = $r['score'];
                $job->matched_keywords = $r['matched'];
                $job->personalized_match = $r['matches'];
                $job->match_tier = $r['tier'];
                $job->match_title_hits = $r['title_hits'];
                $job->match_generic = $r['generic_hits'];
                $job->match_warnings = $r['warnings'];

                return $job;
            })
            ->filter(fn (JobListing $j) => $j->personalized_match)
            ->sortBy($sort === 'match'
                ? [fn ($a, $b) => $b->match_score <=> $a->match_score, fn ($a, $b) => $b->posted_at <=> $a->posted_at]
                : [fn ($a, $b) => $b->posted_at <=> $a->posted_at, fn ($a, $b) => $b->match_score <=> $a->match_score])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $posts = new LengthAwarePaginator(
            $scored->forPage($page, $perPage)->values(),
            $scored->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('social.index', [
            'posts' => $posts,
            'profile' => $profile,
            'filters' => compact('window', 'platform', 'how', 'sort', 'keyword', 'showApplied'),
            'sources' => config('social.sources', []),
            'xEnabled' => filled(config('social.x.bearer_token')),
        ]);
    }

    // POST /social/{job}/send — "apply instantly": tailored resume + cover letter to the address in the post.
    public function send(Request $request, JobListing $job, ResumeTailor $tailor, CoverLetterWriter $writer, ResumeCompiler $compiler)
    {
        abort_unless($job->kind === 'social', 404);

        if ($job->apply_method !== 'email' || ! $job->apply_email || $job->needs_manual_input) {
            return back()->withErrors(['send' => $job->needs_manual_input
                ? 'This post asks for more than a CV and cover letter (see its instructions), so apply manually.'
                : 'This post has no email to apply to. Use the link instead.']);
        }

        $userId = $request->user()->id;
        $resume = Resume::current($userId);
        if (! $resume) {
            return back()->withErrors(['send' => 'Import your resume first (Resume page).']);
        }

        if (ApplicationDraft::where('user_id', $userId)->where('job_listing_id', $job->id)->where('status', 'sent')->exists()) {
            return back()->withErrors(['send' => 'You already sent an application for this post.']);
        }

        $snapshot = $tailor->tailorFor($resume, $job);
        $letter = $snapshot ? $writer->write($resume, $job, $snapshot) : null;
        if (! $snapshot || ! $letter) {
            return back()->withErrors(['send' => 'The AI step failed, nothing was sent. Try again in a moment.']);
        }

        try {
            $pdf = $compiler->compile($resume, $snapshot, "job-{$job->id}-user-{$userId}");
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => 'Could not build the tailored resume PDF: ' . $e->getMessage()]);
        }

        try {
            Mail::to($job->apply_email)->send(new JobApplicationMail($job, $letter['cover_letter'], $pdf));
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => 'Sending failed: ' . $e->getMessage()]);
        }

        ApplicationDraft::updateOrCreate(
            ['job_listing_id' => $job->id, 'user_id' => $userId],
            [
                'cover_letter' => $letter['cover_letter'],
                'tailored_summary' => $letter['tailored_summary'] ?? null,
                'resume_snapshot' => $snapshot,
                'resume_pdf_path' => $pdf,
                'status' => 'sent',
                'apply_channel' => 'email',
                'submitted_at' => now(),
            ]
        );
        JobListingUserState::updateOrCreate(
            ['job_listing_id' => $job->id, 'user_id' => $userId],
            ['is_applied' => true, 'applied_at' => now()]
        );

        return back()->with('status', "Application sent to {$job->apply_email} with a resume tailored for this role.");
    }
}
