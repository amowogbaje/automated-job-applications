<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\RunDiscoveryJob;
use App\Models\DiscoveryRun;
use App\Models\Service;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Discovery\Strategies;
use App\Services\Agent\Search\SearchManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscoveryController extends Controller
{
    public function index(AiManager $ai, SearchManager $search)
    {
        return view('agent.discovery.index', [
            'strategies' => Strategies::ALL,
            'services' => Service::active()->get(),
            'runs' => DiscoveryRun::with('service')->latest('id')->limit(15)->get(),
            'aiReady' => (bool) $ai->activeNames(),
            'searchReady' => (bool) $search->activeNames(),
            'runsToday' => $this->runsToday(),
            'dailyCap' => (int) config('discovery.runs_per_day'),
            'maxQueries' => (int) config('discovery.max_queries_per_run'),
        ]);
    }

    public function store(Request $request, AiManager $ai, SearchManager $search): RedirectResponse
    {
        $data = $request->validate([
            'strategies' => ['required', 'array', 'min:1', 'max:5'],
            'strategies.*' => ['string', Rule::in(Strategies::keys())],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'extra_queries' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $ai->activeNames()) {
            return back()->withErrors(['discovery' => 'No AI provider is enabled with an API key. Open Providers to add one.'])->withInput();
        }
        if (! $search->activeNames()) {
            return back()->withErrors(['discovery' => 'No search provider is enabled with an API key. Discovery needs web search. Open Providers to add one.'])->withInput();
        }

        $extra = collect(preg_split('/\R/', (string) ($data['extra_queries'] ?? '')))->map(fn ($q) => trim($q))->filter()->take(5)->values()->all();
        if (! $data['service_id'] && ! $extra) {
            return back()->withErrors(['service_id' => 'Pick a service, or write at least one search query of your own.'])->withInput();
        }

        $cap = (int) config('discovery.runs_per_day');
        if ($this->runsToday() + count($data['strategies']) > $cap) {
            return back()->withErrors(['discovery' => "That would go over today's limit of {$cap} discovery runs (it protects your free search and AI quotas)."])->withInput();
        }

        $runs = [];
        foreach ($data['strategies'] as $strategy) {
            $runs[] = DiscoveryRun::create([
                'strategy' => $strategy,
                'service_id' => $data['service_id'] ?? null,
                'status' => 'pending',
                'options' => ['extra_queries' => $extra, 'auto_research' => $request->boolean('auto_research')],
            ]);
        }

        foreach ($runs as $run) {
            config('research.mode') === 'sync' ? RunDiscoveryJob::dispatchSync($run->id) : RunDiscoveryJob::dispatch($run->id);
        }

        if (count($runs) === 1) {
            return redirect()->route('agent.discovery.show', $runs[0])
                ->with('status', config('research.mode') === 'sync' ? 'Discovery finished.' : 'Discovery queued. It runs in the background (needs the queue cron).');
        }

        return redirect()->route('agent.discovery.index')->with('status', count($runs).' discovery runs '.(config('research.mode') === 'sync' ? 'finished.' : 'queued.'));
    }

    public function show(DiscoveryRun $run)
    {
        $run->load(['service', 'opportunities.company']);

        return view('agent.discovery.show', [
            'run' => $run,
            'strategy' => Strategies::get($run->strategy),
            'payload' => $this->payload($run),
        ]);
    }

    public function status(DiscoveryRun $run): JsonResponse
    {
        return response()->json($this->payload($run));
    }

    private function payload(DiscoveryRun $run): array
    {
        return ['id' => $run->id, 'status' => $run->status, 'url' => route('agent.discovery.status', $run)];
    }

    private function runsToday(): int
    {
        return DiscoveryRun::where('created_at', '>=', now()->startOfDay())->count();
    }
}
