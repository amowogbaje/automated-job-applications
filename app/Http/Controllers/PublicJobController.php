<?php

namespace App\Http\Controllers;

use App\Models\CareerProfile;
use App\Models\JobApplication;
use App\Models\JobListing;
use Illuminate\Http\Request;

class PublicJobController extends Controller
{
    // GET /job/{job} — public page for jobs posted on this site (shareable, readable without an account).
    public function show(Request $request, JobListing $job)
    {
        abort_unless($job->isNative(), 404);

        $user = $request->user();
        $isOwner = $user && $job->employer_id === $user->id;

        // Drafts are only visible to their owner; closed/expired ones stay readable with a banner.
        abort_if($job->status === 'draft' && ! $isOwner, 404);

        $closed = $job->status === 'closed' || ($job->closes_at && $job->closes_at->isPast());

        $match = null;
        $application = null;
        if ($user && $user->isSeeker()) {
            $r = CareerProfile::forUser($user->id)->score($job->scoringTitle(), $job->scoringDescription());
            $match = $r['matches'] ? $r : null;
            $application = JobApplication::where('job_listing_id', $job->id)->where('user_id', $user->id)->first();
        }

        return view('jobs.public', [
            'job' => $job->load('employer:id,company_name,company_website,company_size,company_description'),
            'closed' => $closed,
            'isOwner' => $isOwner,
            'match' => $match,
            'application' => $application,
            'applicantCount' => $isOwner ? $job->applications()->count() : null,
        ]);
    }
}
