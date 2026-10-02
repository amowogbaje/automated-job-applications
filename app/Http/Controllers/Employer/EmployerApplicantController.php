<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class EmployerApplicantController extends Controller
{
    // GET /employer/applicants?job=ID&status=new
    public function index(Request $request)
    {
        $user = $request->user();
        $myJobs = $user->postedJobs()->orderByDesc('posted_at')->get(['id', 'title', 'status']);

        $jobFilter = $request->integer('job') ?: null;
        $status = $request->query('status', 'all');

        $applications = JobApplication::query()
            ->with(['job:id,title', 'applicant:id,name,email', 'resume.skills'])
            ->whereIn('job_listing_id', $myJobs->pluck('id'))
            ->when($jobFilter, fn ($q) => $q->where('job_listing_id', $jobFilter))
            ->when($status !== 'all' && isset(JobApplication::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->latest('applied_at')
            ->limit(200)
            ->get();

        $counts = JobApplication::whereIn('job_listing_id', $myJobs->pluck('id'))
            ->when($jobFilter, fn ($q) => $q->where('job_listing_id', $jobFilter))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('employer.applicants', compact('applications', 'myJobs', 'jobFilter', 'status', 'counts'));
    }

    // PATCH /employer/applications/{application}
    public function update(Request $request, JobApplication $application)
    {
        $this->authorizeApplication($request, $application);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(JobApplication::STATUSES))],
            'employer_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $application->update($data);

        return back()->with('status', "Updated {$application->applicant->name}'s application.");
    }

    // GET /employer/applications/{application}/resume
    public function resume(Request $request, JobApplication $application)
    {
        $this->authorizeApplication($request, $application);

        if (! $application->resume_pdf_path || ! file_exists($application->resume_pdf_path)) {
            abort(404, 'No resume PDF was attached to this application. Their cover letter and profile are shown on the applicants page.');
        }

        $name = Str::slug($application->applicant->name . '-' . $application->job->title) . '.pdf';

        return Response::file($application->resume_pdf_path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline') . "; filename=\"{$name}\"",
        ]);
    }

    private function authorizeApplication(Request $request, JobApplication $application): void
    {
        $job = JobListing::find($application->job_listing_id);
        abort_unless($job && $job->employer_id === $request->user()->id, 404);
    }
}
