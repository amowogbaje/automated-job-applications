<?php

namespace App\Http\Controllers;

use App\Models\CareerProfile;
use App\Models\JobListing;
use App\Models\JobListingUserState;
use App\Models\Resume;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class JobDashboardController extends Controller
{
    private const PER_PAGE = 30;

    /**
     * GET /jobs — the unified feed: job boards AND social posts, only for the skills this account
     * chose. Matches were decided at ingest (jobs:match) and stored on the user's state rows, so this
     * is a plain indexed query rather than scoring hundreds of listings per page view.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = CareerProfile::forUser($user->id);

        $keyword = trim((string) $request->query('q', ''));
        $window = in_array($request->query('window'), ['24', '72', '168', '336', 'all'], true) ? $request->query('window') : '168';
        $sort = $request->query('sort') === 'recent' ? 'recent' : 'match';
        $tier = in_array($request->query('tier'), ['strong', 'good'], true) ? $request->query('tier') : 'all';
        $source = in_array($request->query('source'), ['boards', 'social'], true) ? $request->query('source') : 'all';
        $status = $request->query('status') === 'applied' ? 'applied' : 'open';

        $base = fn () => JobListingUserState::query()
            ->where('user_id', $user->id)
            ->whereNotNull('matched_at')
            ->where('is_dismissed', false)
            ->when($tier === 'strong', fn ($q) => $q->where('tier', 'strong'))
            ->when($tier === 'good', fn ($q) => $q->whereIn('tier', ['strong', 'good']))
            ->whereHas('job', fn ($q) => $q->canonical()
                ->when($window !== 'all', fn ($q) => $q->where('posted_at', '>=', now()->subHours((int) $window)))
                ->where(fn ($q) => $q->whereNull('posted_at')->orWhere('posted_at', '>=', now()->subDays((int) config('jobs.max_age_days', 30))))
                ->matching($keyword ?: null));

        $withSource = fn ($query, string $which) => $query->whereHas('job', fn ($q) => $which === 'social' ? $q->where('kind', 'social') : $q->where('kind', '!=', 'social'));

        // Tab counts ignore the source filter itself so the numbers don't jump around while switching.
        $open = fn () => $base()->where('is_applied', false);
        $counts = [
            'all' => $open()->count(),
            'boards' => $withSource($open(), 'boards')->count(),
            'social' => $withSource($open(), 'social')->count(),
            'applied' => $base()->where('is_applied', true)->count(),
        ];

        $query = $base()->where('is_applied', $status === 'applied');
        if ($source !== 'all') {
            $withSource($query, $source);
        }

        $postedAt = JobListing::query()->select('posted_at')->whereColumn('job_listings.id', 'job_listing_user_states.job_listing_id');

        $states = $query->with('job')
            ->when($sort === 'recent',
                fn ($q) => $q->orderByDesc($postedAt)->orderByDesc('score'),
                fn ($q) => $q->orderByDesc('score')->orderByDesc($postedAt))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('jobs.index', [
            'states' => $states, 'profile' => $profile, 'counts' => $counts,
            'keyword' => $keyword, 'window' => $window, 'sort' => $sort, 'tier' => $tier,
            'source' => $source, 'status' => $status,
            'hasInterests' => $profile->hasInterests(),
        ]);
    }

    public function dismiss(Request $request, JobListing $job)
    {
        JobListingUserState::updateOrCreate(
            ['job_listing_id' => $job->id, 'user_id' => $request->user()->id],
            ['is_dismissed' => true, 'dismissed_at' => now()]
        );

        return back();
    }

    public function markApplied(Request $request, JobListing $job)
    {
        JobListingUserState::updateOrCreate(
            ['job_listing_id' => $job->id, 'user_id' => $request->user()->id],
            ['is_applied' => true, 'applied_at' => now()]
        );

        return back();
    }

    // GET /jobs/{job}/resume — tailors + compiles a resume specifically
    // for this listing's description, on demand. Cached on the job row so
    // repeat clicks (and repeat AI calls/compiles) don't happen unless the
    // active resume changed or ?regenerate=1 is passed.
    public function resume(Request $request, JobListing $job, ResumeTailor $tailor, ResumeCompiler $compiler)
    {
        $resume = $this->requireActiveResume();
        $snapshot = $this->snapshotFor($job, $resume, $tailor, $request->boolean('regenerate'));

        $stale = $job->tailored_for_resume_id !== $resume->id
            || ! $job->tailored_resume_path
            || ! file_exists($job->tailored_resume_path)
            || $request->boolean('regenerate');

        if ($stale) {
            $path = $compiler->compile($resume, $snapshot, $job->title . '-' . $job->company);

            $job->update([
                'tailored_resume_path' => $path,
                'tailored_resume_snapshot' => $snapshot,
                'tailored_for_resume_id' => $resume->id,
                'tailored_resume_generated_at' => now(),
            ]);
        }

        $filename = Str::slug($resume->full_name . '-' . $job->company) . '.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return Response::file($job->tailored_resume_path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    // GET /jobs/{job}/cover-letter — same on-demand-and-cached pattern as
    // the resume above, so a job's tailored resume and cover letter can be
    // viewed and downloaded as a pair, not just bundled into a draft.
    public function coverLetter(Request $request, JobListing $job, ResumeTailor $tailor, CoverLetterWriter $writer)
    {
        $resume = $this->requireActiveResume();

        if (! $job->description) {
            abort(404, 'This listing has no description to tailor a cover letter against.');
        }

        $stale = $job->tailored_for_resume_id !== $resume->id
            || ! $job->tailored_cover_letter
            || $request->boolean('regenerate');

        if ($stale) {
            $snapshot = $this->snapshotFor($job, $resume, $tailor, $request->boolean('regenerate'));
            $result = $writer->write($resume, $job, $snapshot);

            if (! $result) {
                abort(502, 'The AI provider failed to generate a cover letter — try again in a moment.');
            }

            $job->update([
                'tailored_cover_letter' => $result['cover_letter'],
                'tailored_cover_letter_generated_at' => now(),
                'tailored_for_resume_id' => $resume->id,
            ]);
        }

        if ($request->boolean('download')) {
            $filename = Str::slug($resume->full_name . '-cover-letter-' . $job->company) . '.txt';

            return Response::make($job->tailored_cover_letter, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        return view('jobs.cover-letter', ['job' => $job]);
    }

    private function requireActiveResume(): Resume
    {
        $resume = Resume::current();

        if (! $resume) {
            abort(404, 'No active resume yet — import or claim one at /resume/upload first.');
        }

        return $resume;
    }

    // Reuses the job's cached tailoring snapshot (skills/projects to lead
    // with) rather than re-running the AI tailoring call every time the
    // resume and cover letter are generated back to back for the same job.
    private function snapshotFor(JobListing $job, Resume $resume, ResumeTailor $tailor, bool $forceRegenerate): ?array
    {
        if (! $job->description) {
            return null;
        }

        if (! $forceRegenerate && $job->tailored_for_resume_id === $resume->id && $job->tailored_resume_snapshot) {
            return $job->tailored_resume_snapshot;
        }

        return $tailor->tailorFor($resume, $job);
    }
}
