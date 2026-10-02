<?php

namespace App\Http\Controllers;

use App\Mail\NewApplicationMail;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\JobListingUserState;
use App\Models\Resume;
use App\Services\Resume\CoverLetterWriter;
use App\Services\Resume\ResumeCompiler;
use App\Services\Resume\ResumeTailor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Seeker side of applying to jobs posted on this site. */
class JobApplyController extends Controller
{
    // GET /job/{job}/apply
    public function create(Request $request, JobListing $job)
    {
        if ($redirect = $this->blocked($request, $job)) {
            return $redirect;
        }

        return view('jobs.apply', ['job' => $job, 'resume' => Resume::current($request->user()->id)]);
    }

    // POST /job/{job}/apply/draft-letter — fills the cover-letter box (real experience only, same writer as everywhere else)
    public function draftLetter(Request $request, JobListing $job, ResumeTailor $tailor, CoverLetterWriter $writer)
    {
        abort_unless($this->applicable($job), 404);
        $resume = Resume::current($request->user()->id);
        if (! $resume) {
            return response()->json(['error' => 'Import your resume first.'], 422);
        }

        $snapshot = $this->snapshot($request->user()->id, $job, $resume, $tailor);
        $letter = $snapshot ? $writer->write($resume, $job, $snapshot) : null;
        if (! $letter) {
            return response()->json(['error' => 'The AI step failed. Write your own, or try again in a moment.'], 503);
        }

        return response()->json(['cover_letter' => $letter['cover_letter']]);
    }

    // POST /job/{job}/apply
    public function store(Request $request, JobListing $job, ResumeTailor $tailor, ResumeCompiler $compiler)
    {
        if ($redirect = $this->blocked($request, $job)) {
            return $redirect;
        }

        $data = $request->validate(['cover_letter' => ['required', 'string', 'min:40', 'max:5000']]);
        $resume = Resume::current($request->user()->id);
        $pdf = null;

        if ($request->boolean('tailor', true)) {
            try {
                $snapshot = $this->snapshot($request->user()->id, $job, $resume, $tailor);
                $pdf = $compiler->compile($resume, $snapshot, "apply-{$job->id}-user-{$request->user()->id}");
            } catch (\Throwable $e) {
                Log::warning('Tailored resume failed for in-app application', ['job' => $job->id, 'error' => $e->getMessage()]);
            }
        }
        // Fall back to the untailored resume PDF rather than sending nothing.
        if (! $pdf) {
            try {
                $pdf = $compiler->compile($resume, null, "apply-{$job->id}-user-{$request->user()->id}");
            } catch (\Throwable $e) {
                Log::warning('Resume compile failed for in-app application', ['job' => $job->id, 'error' => $e->getMessage()]);
            }
        }

        $application = $this->record($request->user()->id, $job, $resume->id, $data['cover_letter'], $pdf);

        return redirect()->route('applications.index')->with('status', "Applied to {$job->title} at {$job->company}. You'll see updates here when they review it.");
    }

    /** Shared by the form and by the auto-apply driver. */
    public function record(int $userId, JobListing $job, ?int $resumeId, string $coverLetter, ?string $pdf): JobApplication
    {
        $application = JobApplication::firstOrCreate(
            ['job_listing_id' => $job->id, 'user_id' => $userId],
            ['resume_id' => $resumeId, 'cover_letter' => $coverLetter, 'resume_pdf_path' => $pdf, 'status' => 'new', 'applied_at' => now()]
        );

        JobListingUserState::updateOrCreate(['job_listing_id' => $job->id, 'user_id' => $userId], ['is_applied' => true, 'applied_at' => now()]);

        if ($application->wasRecentlyCreated && $job->employer) {
            try {
                Mail::to($job->employer->email)->send(new NewApplicationMail($application->load(['job', 'applicant'])));
            } catch (\Throwable $e) {
                // The application is saved either way; the employer still sees it on their dashboard.
                Log::warning('Could not email employer about a new application', ['job' => $job->id, 'error' => $e->getMessage()]);
            }
        }

        return $application;
    }

    private function applicable(JobListing $job): bool
    {
        return $job->isNative() && $job->apply_method === 'native' && $job->status === 'published' && (! $job->closes_at || $job->closes_at->isFuture());
    }

    private function blocked(Request $request, JobListing $job)
    {
        if (! $job->isNative() || $job->apply_method !== 'native') {
            return redirect($job->apply_url ?: $job->url);
        }
        if (! $this->applicable($job)) {
            return redirect()->route('jobs.public', $job)->withErrors(['apply' => 'This job is no longer accepting applications.']);
        }
        if (! Resume::current($request->user()->id)) {
            return redirect()->route('resume.upload')->withErrors(['resume' => 'Import your resume first so there is something to send.']);
        }
        if (JobApplication::where('job_listing_id', $job->id)->where('user_id', $request->user()->id)->exists()) {
            return redirect()->route('applications.index')->with('status', 'You already applied to this job.');
        }

        return null;
    }

    /** Tailoring costs an AI call; reuse it between "draft letter" and "submit" for half an hour. */
    private function snapshot(int $userId, JobListing $job, Resume $resume, ResumeTailor $tailor): ?array
    {
        return Cache::remember("apply.snapshot.{$userId}.{$job->id}.{$resume->id}", now()->addMinutes(30), fn () => $tailor->tailorFor($resume, $job));
    }
}
