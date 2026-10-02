<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class EmployerDashboardController extends Controller
{
    // GET /employer
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $jobIds = $user->postedJobs()->pluck('id');

        $apps = JobApplication::whereIn('job_listing_id', $jobIds);

        return view('employer.dashboard', [
            'stats' => [
                'live' => $user->postedJobs()->where('status', 'published')->count(),
                'drafts' => $user->postedJobs()->where('status', 'draft')->count(),
                'applicants' => (clone $apps)->count(),
                'new' => (clone $apps)->where('status', 'new')->count(),
                'shortlisted' => (clone $apps)->where('status', 'shortlisted')->count(),
            ],
            'recent' => JobApplication::with(['job:id,title', 'applicant:id,name,email'])
                ->whereIn('job_listing_id', $jobIds)->latest('applied_at')->limit(6)->get(),
            'jobs' => $user->postedJobs()->where('status', 'published')
                ->withCount(['applications', 'applications as new_applications_count' => fn ($q) => $q->where('status', 'new')])
                ->latest('posted_at')->limit(5)->get(),
        ]);
    }

    // GET /employer/company
    public function company(Request $request)
    {
        return view('employer.company', ['user' => $request->user()]);
    }

    // PATCH /employer/company
    public function updateCompany(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_website' => ['nullable', 'url', 'max:255'],
            'company_size' => ['nullable', 'in:1-10,11-50,51-200,201-1000,1000+'],
            'company_description' => ['nullable', 'string', 'max:2000'],
        ]);

        $request->user()->update($data);

        // Company name is shown on every live job, so keep them in step.
        $request->user()->postedJobs()->update(['company' => $data['company_name']]);

        return back()->with('status', 'Company profile saved. Your live jobs show the new details.');
    }
}
