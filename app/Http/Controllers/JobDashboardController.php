<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\Resume;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class JobDashboardController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->query('q');
        $window = $request->query('window', '24'); // hours

        $jobs = JobListing::query()
            ->notDismissed()
            ->when($window !== 'all', fn ($q) => $q->where('posted_at', '>=', now()->subHours((int) $window)))
            ->matching($keyword)
            ->orderByDesc('posted_at')
            ->orderByDesc('match_score')
            ->paginate(30)
            ->withQueryString();

        $sources = JobListing::query()->distinct()->pluck('source');

        return view('jobs.index', compact('jobs', 'keyword', 'window', 'sources'));
    }

    public function dismiss(JobListing $job)
    {
        $job->update(['is_dismissed' => true]);
        return back();
    }

    public function markApplied(JobListing $job)
    {
        $job->update(['is_applied' => true]);
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
