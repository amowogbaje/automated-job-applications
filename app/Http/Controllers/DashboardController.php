<?php

namespace App\Http\Controllers;

use App\Models\CvVersion;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Signature;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $counts = [
            'services' => Service::where('is_active', true)->count(),
            'projects' => PortfolioProject::count(),
            'cvs' => CvVersion::count(),
            'signatures' => Signature::count(),
        ];

        // Setup checklist: what the agent needs before it can do useful work
        $setup = [
            ['Define at least one active service', $counts['services'] > 0, route('services.index')],
            ['Add portfolio projects', $counts['projects'] > 0, route('projects.index')],
            ['Add a CV version', $counts['cvs'] > 0, route('cvs.index')],
            ['Create an email signature', $counts['signatures'] > 0, route('signatures.index')],
        ];

        $pipeline = Opportunity::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('dashboard', [
            'counts' => $counts,
            'setup' => $setup,
            'pipeline' => $pipeline,
            'topOpportunities' => Opportunity::actionable()->whereNotNull('total_score')
                ->with('company')->orderByDesc('total_score')->limit(6)->get(),
            'followUps' => FollowUp::whereNull('completed_at')->where('due_at', '<=', now()->addDays(7))
                ->with('opportunity.company')->orderBy('due_at')->limit(8)->get(),
        ]);
    }
}
