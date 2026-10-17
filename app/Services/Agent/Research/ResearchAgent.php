<?php

namespace App\Services\Agent\Research;

use App\Models\ActivityLog;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Contact;
use App\Models\CvVersion;
use App\Models\Evidence;
use App\Models\Opportunity;
use App\Models\OpportunityScore;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Agent\Ai\AiManager;
use App\Services\Agent\Search\SearchManager;
use Illuminate\Support\Facades\DB;

/**
 * Research pipeline for one opportunity. Every step is logged to agent_steps.
 * Contacts come only from pages we read; evidence must cite a real source and quotes are verified;
 * human-entered fields and existing scores are never overwritten.
 */
class ResearchAgent
{
    private int $stepNo = 0;

    public function __construct(private AiManager $ai, private SearchManager $search) {}

    public function run(AgentRun $run): void
    {
        $o = Opportunity::with(['company', 'services', 'primaryService'])->findOrFail($run->opportunity_id);
        $company = $o->company;
        $cfg = config('research') + ['results_per_query' => (int) config('search.results_per_query', 5)];
        $this->stepNo = 0;

        $run->update(['status' => 'running', 'started_at' => now()]);
        if ($o->status === 'discovered') {
            $o->update(['status' => 'researching']);
        }
        ActivityLog::record($o, 'research_started', "Research run #{$run->id} started.");

        try {
            $this->step($run, 'CheckExistingLead', ['company' => $company->name], function () use ($company, $o) {
                $n = Opportunity::where('company_id', $company->id)->where('id', '!=', $o->id)->count();

                return [$n, "Company has {$n} other opportunity record(s)."];
            });

            // 1. Read the company's own pages
            $pages = [];
            if ($company->website_url) {
                $pages = $this->step($run, 'InspectWebsite', ['url' => $company->website_url], function () use ($company, $cfg) {
                    $r = (new SiteInspector)->inspect($company->website_url, (int) $cfg['pages_per_site']);
                    $summary = count($r['pages']).' page(s) read: '.implode(', ', array_column($r['pages'], 'url')).($r['notes'] ? ' | '.implode(' ', $r['notes']) : '');

                    return [$r['pages'], $summary];
                }) ?? [];
            }

            // 2. Search the web
            $results = [];
            foreach ($this->queries($company, $o) as $q) {
                $res = $this->step($run, 'SearchWeb', ['query' => $q], function () use ($q, $cfg) {
                    $r = $this->search->search($q, (int) $cfg['results_per_query']);

                    return [$r, count($r['results']).' result(s) via '.$r['provider']];
                });
                foreach ($res['results'] ?? [] as $r) {
                    $results[$r['url']] = $r;
                }
            }

            // 3. Contacts the company itself published
            $contacts = $this->step($run, 'FindBusinessContact', ['pages' => count($pages)], function () use ($pages, $company, $o) {
                $found = ContactCollector::collect($pages, (string) $company->website_url);
                $new = 0;
                foreach ($found as $c) {
                    $row = Contact::firstOrCreate(
                        ['company_id' => $company->id, 'type' => $c['type'], 'value' => $c['value']],
                        [
                            'opportunity_id' => $o->id, 'label' => $c['label'], 'source_url' => $c['source_url'],
                            'verified_at' => now(), 'is_public_business_channel' => $c['is_public_business_channel'], 'created_by' => 'agent',
                        ]
                    );
                    $new += $row->wasRecentlyCreated ? 1 : 0;
                }

                return [$found, count($found).' published contact route(s) found, '.$new.' new.'];
            }) ?? [];

            // 4. Ask the AI to analyse only what we gathered
            $sources = $this->buildSources($pages, $results, $cfg);
            if (! $sources) {
                throw new \RuntimeException('Nothing to analyse: no website pages could be read and search returned nothing. Add the company website and check that a search provider is enabled on the Providers page.');
            }

            $services = Service::active()->get();
            $resp = $this->step($run, 'AnalyseEvidence', ['sources' => array_keys($sources)], function () use ($company, $o, $services, $sources) {
                $messages = ResearchPrompt::build(
                    ['name' => $company->name, 'website' => $company->website_url ?: 'unknown', 'industry' => $company->industry ?: 'unknown', 'country' => $company->country ?: 'unknown'],
                    ['title' => $o->title, 'type' => $o->type, 'notes' => trim(($o->summary ?? '').' '.($o->business_need ?? '')) ?: 'none'],
                    $services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'skills' => $s->skills_required ?? [], 'signals' => $s->opportunity_signals ?? []])->all(),
                    $sources
                );
                $r = $this->ai->complete($messages, json: true, maxTokens: 3000);

                return [$r, "Analysed by {$r->provider} ({$r->model}); ".$r->inputTokens.' in / '.$r->outputTokens.' out tokens.'.$this->failoverNote($r->attempts)];
            }, critical: true);

            // 5. Save results
            $summary = $this->step($run, 'SaveResearchEvidence', ['opportunity' => $o->id], function () use ($o, $services, $resp, $sources, $pages) {
                $text = $this->persist($o->fresh(['company', 'services']), $services, $resp->data ?? [], $sources, $pages);

                return [$text, $text];
            }, critical: true);

            $run->update([
                'status' => 'completed', 'finished_at' => now(),
                'ai_model' => $resp->provider.'/'.$resp->model,
                'input_tokens' => $resp->inputTokens, 'output_tokens' => $resp->outputTokens,
                'result_summary' => $summary,
            ]);
            ActivityLog::record($o, 'research_completed', "Research run #{$run->id} finished. Review the findings, then move it to Qualified or Rejected.");
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            ActivityLog::record($o, 'research_failed', 'Research failed: '.mb_substr($e->getMessage(), 0, 200));
        }
    }

    /** Runs one logged step. $fn returns [result, summaryText]. A non-critical failure returns null. */
    private function step(AgentRun $run, string $tool, array $input, callable $fn, bool $critical = false): mixed
    {
        $this->stepNo++;
        $started = microtime(true);
        $step = AgentStep::create(['agent_run_id' => $run->id, 'step_number' => $this->stepNo, 'tool' => $tool, 'input' => $input]);
        $run->update(['steps_used' => $this->stepNo]);

        try {
            [$result, $summary] = $fn();
            $step->update(['output' => mb_substr((string) $summary, 0, 4000), 'duration_ms' => (int) round((microtime(true) - $started) * 1000)]);

            return $result;
        } catch (\Throwable $e) {
            $step->update(['succeeded' => false, 'error' => mb_substr($e->getMessage(), 0, 1000), 'duration_ms' => (int) round((microtime(true) - $started) * 1000)]);
            if ($critical) {
                throw $e;
            }

            return null;
        }
    }

    private function queries($company, Opportunity $o): array
    {
        $kw = data_get($o->primaryService, 'search_keywords.0')
            ?? collect($o->services)->flatMap(fn ($s) => $s->search_keywords ?? [])->first();

        $queries = [
            "{$company->name} {$company->industry} company services",
            "{$company->name} hiring developer OR engineer OR \"looking for\" ".($kw ?? 'software'),
            "{$company->name} news announcement funding OR expansion OR launch",
        ];

        return array_slice(array_map(fn ($q) => trim(preg_replace('/\s+/', ' ', $q)), $queries), 0, (int) config('search.queries_per_run', 3));
    }

    /** Label sources S1, S2... Pages first, then search results, within the prompt budget. */
    private function buildSources(array $pages, array $results, array $cfg): array
    {
        $sources = [];
        $budget = (int) $cfg['max_prompt_chars'];
        $i = 1;

        foreach ($pages as $p) {
            $text = mb_substr($p['text'], 0, (int) $cfg['page_chars']);
            if ($text === '' || $budget < 500) {
                continue;
            }
            $sources['S'.$i++] = ['url' => $p['url'], 'title' => $p['title'], 'text' => $text];
            $budget -= mb_strlen($text);
        }
        foreach ($results as $r) {
            $text = trim($r['title'].' - '.$r['snippet']);
            if ($budget < 300 || mb_strlen($r['snippet']) < 20) {
                continue;
            }
            $sources['S'.$i++] = ['url' => $r['url'], 'title' => $r['title'], 'text' => $text];
            $budget -= mb_strlen($text);
        }

        return $sources;
    }

    private function persist(Opportunity $o, $services, array $data, array $sources, array $pages): string
    {
        $company = $o->company;
        $validServiceIds = $services->pluck('id')->all();
        $report = [];

        DB::transaction(function () use ($o, $company, $services, $data, $sources, $pages, $validServiceIds, &$report) {
            // Evidence: cited, source-checked, quotes verified
            $rows = EvidenceVerifier::filter((array) ($data['evidence'] ?? []), $sources, array_keys(config('opportunity.evidence_kinds')));
            $added = 0;
            foreach ($rows as $r) {
                $exists = Evidence::where('opportunity_id', $o->id)->where('source_url', $r['source_url'])->where('finding', $r['finding'])->exists();
                if ($exists) {
                    continue;
                }
                unset($r['source_id']);
                Evidence::create($r + ['opportunity_id' => $o->id, 'company_id' => $company->id, 'retrieved_at' => now(), 'created_by' => 'agent']);
                $added++;
            }
            $report[] = "{$added} evidence item(s)";

            // Text fields: only fill what is blank
            $update = [];
            if (blank($o->business_need) && filled($data['business_need'] ?? null)) {
                $update['business_need'] = mb_substr((string) $data['business_need'], 0, 3000);
            }
            if (blank($o->inferred_opportunity) && filled($data['inferred_opportunity'] ?? null)) {
                $update['inferred_opportunity'] = mb_substr((string) $data['inferred_opportunity'], 0, 3000);
            }
            if (blank($o->confidence) && in_array($data['confidence'] ?? '', EvidenceVerifier::CONFIDENCE, true)) {
                $update['confidence'] = $data['confidence'];
            }
            $action = trim((string) ($data['recommended_action'] ?? ''));
            if (! empty($data['should_reject']) && filled($data['reject_reason'] ?? null)) {
                $action = 'Consider rejecting: '.trim((string) $data['reject_reason']).($action ? ' | '.$action : '');
                $report[] = 'AI suggests rejecting';
            }
            if (blank($o->recommended_action) && $action !== '') {
                $update['recommended_action'] = mb_substr($action, 0, 2000);
            }

            // Service match: keep what you chose; fill gaps
            $primaryId = (int) ($data['primary_service_id'] ?? 0);
            if (! $o->primary_service_id && in_array($primaryId, $validServiceIds, true)) {
                $update['primary_service_id'] = $primaryId;
            }
            foreach ((array) ($data['matched_services'] ?? []) as $m) {
                $sid = (int) ($m['service_id'] ?? 0);
                if (in_array($sid, $validServiceIds, true) && ! $o->services->contains('id', $sid)) {
                    $o->services()->attach($sid, [
                        'match_strength' => max(0, min(100, (int) ($m['match_strength'] ?? 0))),
                        'is_primary' => $sid === ($update['primary_service_id'] ?? $o->primary_service_id),
                        'rationale' => mb_substr((string) ($m['rationale'] ?? ''), 0, 1000) ?: null,
                    ]);
                }
            }
            if ($update) {
                $o->update($update);
                $o->refresh();
            }

            // Company housekeeping
            $companyUpdate = ['last_inspected_at' => now()];
            if (blank($company->description) && ! empty($pages[0]['description'])) {
                $companyUpdate['description'] = mb_substr($pages[0]['description'], 0, 1000);
            }
            $company->update($companyUpdate);

            // Contacts -> channel and score (deterministic)
            $contacts = Contact::where('company_id', $company->id)->get(['type', 'is_public_business_channel'])->toArray();
            $isEmployment = $o->engagement_type === 'employment' || $o->type === 'job_posting';
            $route = ContactRouting::recommend($contacts, $o->engagement_type, $o->type);
            $channelUpdate = [];
            if (blank($o->recommended_channel) && $route['channel']) {
                $channelUpdate = ['recommended_channel' => $route['channel'], 'channel_rationale' => $route['rationale']];
            }

            // Scores: add only the criteria that have none yet
            $weights = Setting::get('score_weights', config('opportunity.default_weights'));
            $existing = $o->scores()->pluck('criterion')->all();
            $newScores = 0;
            $put = function (string $criterion, int $score, string $why) use ($o, $weights, $existing, &$newScores) {
                if (in_array($criterion, $existing, true)) {
                    return;
                }
                OpportunityScore::create([
                    'opportunity_id' => $o->id, 'criterion' => $criterion, 'score' => max(0, min(100, $score)),
                    'weight' => (int) ($weights[$criterion] ?? 0), 'reasoning' => mb_substr('AI-suggested: '.$why, 0, 1000), 'created_by' => 'agent',
                ]);
                $newScores++;
            };
            foreach (['need_evidence', 'skills_match', 'active_signal', 'commercial_value'] as $c) {
                $s = $data['scores'][$c] ?? null;
                if (is_array($s) && isset($s['score']) && is_numeric($s['score'])) {
                    $put($c, (int) $s['score'], (string) ($s['reasoning'] ?? ''));
                }
            }
            $cs = ContactRouting::score($contacts, $isEmployment);
            $put('contact_channel', $cs['score'], $cs['reasoning']);

            // Portfolio and CV from the service links you configured
            $service = $o->primaryService ?? ($o->primary_service_id ? Service::find($o->primary_service_id) : null);
            if ($service) {
                $projects = $service->portfolioProjects()->orderByDesc('service_portfolio_project.relevance')->get();
                if ($projects->isNotEmpty()) {
                    $top = (int) $projects->first()->pivot->relevance;
                    $put('portfolio_strength', min(100, $top * 20 + ($projects->count() >= 2 ? 10 : 0)), "{$projects->count()} project(s) linked to {$service->name}, strongest relevance {$top}/5.");
                    if (! $o->portfolioProjects()->exists()) {
                        foreach ($projects->take(2)->values() as $i => $p) {
                            $o->portfolioProjects()->attach($p->id, ['rank' => $i + 1, 'reason' => "Linked to {$service->name} (relevance {$p->pivot->relevance}/5)."]);
                        }
                    }
                }
                if (! $o->recommended_cv_version_id) {
                    $cv = $service->cvVersions()->first() ?? CvVersion::where('is_default', true)->first();
                    if ($cv) {
                        $channelUpdate += ['recommended_cv_version_id' => $cv->id, 'cv_rationale' => $service->cvVersions()->where('cv_versions.id', $cv->id)->exists() ? "Linked to the {$service->name} service." : 'Your default CV.'];
                    }
                }
            }
            if ($channelUpdate) {
                $o->update($channelUpdate);
            }

            $report[] = $newScores.' score(s) suggested';
            $report[] = count($contacts).' contact route(s) on file';
        });

        if ($o->scores()->exists()) {
            $o->recalculateScore();
        }

        return implode(', ', $report).'. Suggestions only: review the evidence and adjust scores before acting.';
    }

    private function failoverNote(array $attempts): string
    {
        if (! $attempts) {
            return '';
        }

        return ' Fallbacks: '.implode('; ', array_map(
            fn ($a) => $a['provider'].' '.str_replace('_', ' ', $a['outcome']).($a['detail'] ? ' ('.mb_substr($a['detail'], 0, 80).')' : ''),
            $attempts
        )).'.';
    }
}
