<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    // GET / — public landing page. Signed-in users go straight to their feed.
    public function __invoke(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('jobs.index');
        }

        // Real numbers only (no invented "10,000 applications" claims), cached
        // so a busy landing page doesn't hit the jobs table on every request.
        $stats = Cache::remember('home.stats', 600, fn () => [
            'tracked' => JobListing::count(),
            'last_day' => JobListing::where('created_at', '>=', now()->subDay())->count(),
            'sources' => JobListing::distinct()->count('source'),
        ]);

        $latest = Cache::remember('home.latest', 600, fn () => JobListing::query()
            ->whereNotNull('posted_at')
            ->orderByDesc('posted_at')
            ->limit(5)
            ->get(['title', 'company', 'location', 'source', 'is_remote', 'posted_at']));

        return view('welcome', compact('stats', 'latest'));
    }
}
