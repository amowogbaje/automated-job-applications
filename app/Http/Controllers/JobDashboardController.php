<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use Illuminate\Http\Request;

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
            ->orderByDesc('match_score')
            ->orderByDesc('posted_at')
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
}
