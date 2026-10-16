<?php

namespace App\Http\Controllers;

use App\Jobs\ResearchOpportunityJob;
use App\Models\ActivityLog;
use App\Models\AgentRun;
use App\Models\Opportunity;
use App\Services\Ai\AiManager;
use App\Services\Search\SearchManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ResearchController extends Controller
{
    public function start(Opportunity $opportunity, AiManager $ai, SearchManager $search): RedirectResponse
    {
        $running = $opportunity->agentRuns()->whereIn('status', ['pending', 'running'])
            ->where('created_at', '>', now()->subMinutes(15))->exists();
        if ($running) {
            return back()->withErrors(['research' => 'A research run is already in progress for this opportunity.']);
        }
        if (! $ai->activeNames()) {
            return back()->withErrors(['research' => 'No AI provider is enabled with an API key. Open Providers to add one.']);
        }
        if (! $opportunity->company->website_url && ! $search->activeNames()) {
            return back()->withErrors(['research' => 'Add the company website or enable a search provider first, otherwise there is nothing to research.']);
        }

        $run = $opportunity->agentRuns()->create([
            'company_id' => $opportunity->company_id,
            'kind' => 'research',
            'objective' => 'Research '.$opportunity->company->name.' for: '.$opportunity->title,
            'status' => 'pending',
            'step_limit' => 12,
        ]);
        ActivityLog::record($opportunity, 'research_queued', "Research run #{$run->id} queued.");

        if (config('research.mode') === 'sync') {
            ResearchOpportunityJob::dispatchSync($run->id);

            return back()->with('status', 'Research finished.');
        }
        ResearchOpportunityJob::dispatch($run->id);

        return back()->with('status', 'Research queued. It runs in the background (needs the queue cron) and this page updates when it finishes.');
    }

    public function show(AgentRun $run): JsonResponse
    {
        return response()->json($run->load('steps')->payload());
    }
}
