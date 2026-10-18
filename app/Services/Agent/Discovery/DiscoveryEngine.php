<?php

namespace App\Services\Agent\Discovery;

use App\Jobs\ResearchOpportunityJob;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\Evidence;
use App\Models\Opportunity;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Providers\AllProvidersFailed;
use App\Services\Agent\Providers\DailyLimitReached;
use App\Services\Agent\Providers\ProviderException;
use App\Services\Agent\Search\SearchManager;

/**
 * Finds candidate companies for one strategy + service and saves them as "discovered" opportunities
 * with a verified quote as evidence. It never contacts anyone and never marks anything qualified:
 * qualifying is the research agent's job, and sending is yours.
 */
class DiscoveryEngine
{
    public function __construct(private SearchManager $search, private AiManager $ai) {}

    public function run(DiscoveryRun $run): void
    {
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $opts = (array) ($run->options ?? []);
        $service = $run->service;
        $strategy = $run->strategy;
        $def = Strategies::get($strategy);

        $report = ['seen' => 0, 'known' => 0, 'rejected' => [], 'notes' => []];
        $created = [];

        try {
            $queries = Strategies::queries($strategy, $service, (array) ($opts['extra_queries'] ?? []));
            if (! $queries) {
                throw new \RuntimeException('Nothing to search for. Add search keywords, skills or target businesses to the service, or write your own queries.');
            }
            $run->update(['queries' => $queries]);

            $sources = $this->collect($run, $queries, $report);
            if (! $sources) {
                if ($report['seen'] === 0) {
                    throw new \RuntimeException('The searches returned nothing.');
                }
                $report['notes'][] = 'Every result was already known or not useful, so nothing new was found.';
            }

            $maxNew = (int) config('discovery.max_candidates_per_run', 12);
            foreach ($sources ? array_chunk($sources, max(1, (int) config('discovery.ai_batch', 8)), true) : [] as $batch) {
                if (count($created) >= $maxNew) {
                    break;
                }
                try {
                    $res = $this->ai->complete(
                        DiscoveryPrompt::build($strategy, $service, $batch), true, 2500,
                        fn (array $d) => isset($d['candidates']) && is_array($d['candidates']) ? true : 'Missing "candidates" list.'
                    );
                } catch (AllProvidersFailed|DailyLimitReached $e) {
                    $report['notes'][] = 'AI stopped early: '.$e->getMessage();
                    break;
                }

                foreach ($res->data['candidates'] as $item) {
                    if (! is_array($item) || count($created) >= $maxNew) {
                        continue;
                    }
                    $check = CandidateFilter::check($item, $batch, $def['type']);
                    if (! $check['ok']) {
                        $report['rejected'][$check['reason']] = ($report['rejected'][$check['reason']] ?? 0) + 1;
                        continue;
                    }
                    $o = $this->save($run, $check['data'], $batch[$check['data']['source_id']], $def, $report);
                    if ($o) {
                        $created[] = $o->id;
                    }
                }
            }
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now(),
                'result' => $report + ['created' => $created],
            ]);

            return;
        }

        $run->update([
            'status' => 'completed', 'finished_at' => now(),
            'candidates_found' => count($created) + array_sum($report['rejected']),
            'candidates_new' => count($created),
            'result' => $report + ['created' => $created],
        ]);

        if (! empty($opts['auto_research']) && $created) {
            $this->queueResearch($created);
        }
    }

    /**
     * Search every query, drop junk and already-stored URLs, and label what is left S1, S2, ...
     *
     * @return array<string, array{url: string, title: string, text: string}>
     */
    private function collect(DiscoveryRun $run, array $queries, array &$report): array
    {
        $per = (int) config('discovery.results_per_query', 6);
        $cap = (int) config('discovery.max_candidates_per_run', 12) * 2;
        $sources = [];
        $seenUrls = [];
        $calls = 0;

        foreach ($queries as $q) {
            try {
                $res = $this->search->search($q, $per);
                $calls++;
            } catch (AllProvidersFailed|DailyLimitReached|ProviderException $e) {
                if ($calls === 0) {
                    throw new \RuntimeException('Search failed: '.$e->getMessage());
                }
                $report['notes'][] = 'Search stopped early: '.$e->getMessage();
                break;
            }

            foreach ($res['results'] as $r) {
                $url = $r['url'];
                if (isset($seenUrls[$url])) {
                    continue;
                }
                $seenUrls[$url] = true;
                $report['seen']++;
                if (CandidateFilter::isJunk($url)) {
                    continue;
                }
                if (Opportunity::where('source_url', $url)->exists()) {
                    $report['known']++;
                    continue;
                }
                if (count($sources) < $cap) {
                    $sources['S'.(count($sources) + 1)] = ['url' => $url, 'title' => (string) $r['title'], 'text' => mb_substr((string) $r['snippet'], 0, 1200)];
                }
            }
        }

        $run->update(['api_calls' => $calls]);

        return $sources;
    }

    private function save(DiscoveryRun $run, array $c, array $source, array $def, array &$report): ?Opportunity
    {
        $domain = Company::normalizeDomain($c['website_url']);
        $company = $domain ? Company::where('domain', $domain)->first() : null;
        $company ??= Company::whereRaw('LOWER(name) = ?', [mb_strtolower($c['company_name'])])->first();

        if ($company && $company->do_not_contact) {
            $report['rejected']['company is on your do-not-contact list'] = ($report['rejected']['company is on your do-not-contact list'] ?? 0) + 1;

            return null;
        }
        $company ??= Company::create(array_filter([
            'name' => $c['company_name'], 'website_url' => $c['website_url'],
            'industry' => $c['industry'], 'country' => $c['country'],
        ], fn ($v) => filled($v)));

        $hash = sha1($company->id.'|'.$source['url']);
        if (Opportunity::where('source_hash', $hash)->exists()) {
            $report['known']++;

            return null;
        }

        $o = Opportunity::create([
            'company_id' => $company->id,
            'discovery_run_id' => $run->id,
            'primary_service_id' => $run->service_id,
            'title' => $c['title'],
            'type' => $c['type'],
            'source_url' => $source['url'],
            'source_hash' => $hash,
            'summary' => $c['finding'],
            'business_need' => $c['establishes'],
            'inferred_opportunity' => $c['inference'],
            'confidence' => $c['confidence'],
            'status' => 'discovered',
        ]);
        if ($run->service_id) {
            $o->services()->attach($run->service_id, ['match_strength' => 0, 'is_primary' => true]);
        }

        Evidence::create([
            'opportunity_id' => $o->id, 'company_id' => $company->id, 'kind' => $def['evidence'],
            'source_url' => $source['url'], 'source_title' => mb_substr($source['title'], 0, 250), 'retrieved_at' => now(),
            'finding' => $c['finding'], 'establishes' => $c['establishes'], 'inference' => $c['inference'],
            'confidence' => $c['confidence'], 'excerpt' => $c['excerpt'], 'created_by' => 'agent',
        ]);
        ActivityLog::record($o, 'discovered', "Found by discovery run #{$run->id} ({$def['label']}). Not yet researched.");

        return $o;
    }

    /** Queue the research agent for the newest few candidates (capped; never contacts anyone). */
    private function queueResearch(array $ids): void
    {
        if (! $this->ai->activeNames()) {
            return;
        }
        foreach (array_slice($ids, 0, (int) config('discovery.auto_research_cap', 5)) as $id) {
            $o = Opportunity::with('company')->find($id);
            if (! $o || (! $o->company->website_url && ! $this->search->activeNames())) {
                continue;
            }
            $r = $o->agentRuns()->create([
                'company_id' => $o->company_id, 'kind' => 'research', 'status' => 'pending', 'step_limit' => 12,
                'objective' => 'Research '.$o->company->name.' for: '.$o->title,
            ]);
            ActivityLog::record($o, 'research_queued', "Research run #{$r->id} queued after discovery.");
            ResearchOpportunityJob::dispatch($r->id);
        }
    }
}
