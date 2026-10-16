<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\OpportunityScore;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Ai\AiManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $opportunities = Opportunity::query()
            ->with(['company', 'primaryService'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $like = '%'.$request->input('q').'%';
                $query->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when(! $request->filled('status') && ! $request->boolean('all'),
                fn ($q) => $q->whereNotIn('status', ['rejected', 'archived']))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('service_id'), fn ($q) => $q->whereHas('services',
                fn ($s) => $s->where('services.id', $request->input('service_id'))))
            ->when($request->filled('country'), fn ($q) => $q->whereHas('company',
                fn ($c) => $c->where('country', $request->input('country'))))
            ->when($request->filled('size'), fn ($q) => $q->whereHas('company',
                fn ($c) => $c->where('size_band', $request->input('size'))))
            ->when($request->filled('channel'), fn ($q) => $q->where('recommended_channel', $request->input('channel')))
            ->orderByRaw('total_score IS NULL')
            ->orderByDesc('total_score')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('opportunities.index', [
            'opportunities' => $opportunities,
            'services' => Service::orderBy('name')->get(),
            'countries' => Company::whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'sizes' => Company::whereNotNull('size_band')->distinct()->orderBy('size_band')->pluck('size_band'),
        ]);
    }

    public function create()
    {
        return view('opportunities.form', [
            'opportunity' => new Opportunity(['type' => 'manual', 'budget_currency' => 'USD']),
            'company' => new Company,
            'services' => Service::active()->get(),
            'selectedServices' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $company = $this->resolveCompany($data, overwrite: false);

        $hash = $this->hashFor($company->id, $data);
        if (Opportunity::where('source_hash', $hash)->exists()) {
            return back()->withInput()->withErrors([
                'title' => 'This opportunity already exists for '.$company->name.' (same source or title).',
            ]);
        }

        $opportunity = Opportunity::create($this->opportunityAttributes($data) + [
            'company_id' => $company->id,
            'source_hash' => $hash,
            'status' => 'discovered',
        ]);
        $this->syncServices($opportunity, $data);
        ActivityLog::record($opportunity, 'created', 'Opportunity added manually.');

        return redirect()->route('opportunities.show', $opportunity)->with('status', 'Opportunity created.');
    }

    public function show(Opportunity $opportunity)
    {
        $opportunity->load([
            'company.contacts', 'primaryService', 'services', 'scores', 'evidence',
            'followUps', 'outreachEvents', 'portfolioProjects', 'recommendedCv',
        ]);

        $weights = Setting::get('score_weights', config('opportunity.default_weights'));
        $run = $opportunity->agentRuns()->with('steps')->latest('id')->first();

        return view('opportunities.show', [
            'runPayload' => $run?->payload(),
            'aiReady' => (bool) app(AiManager::class)->activeNames(),
            'opportunity' => $opportunity,
            'company' => $opportunity->company,
            'scores' => $opportunity->scores->keyBy('criterion'),
            'weights' => $weights,
            'activity' => ActivityLog::where('subject_type', Opportunity::class)
                ->where('subject_id', $opportunity->id)->latest()->limit(15)->get(),
        ]);
    }

    public function edit(Opportunity $opportunity)
    {
        return view('opportunities.form', [
            'opportunity' => $opportunity,
            'company' => $opportunity->company,
            'services' => Service::active()->get(),
            'selectedServices' => $opportunity->services->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $this->validated($request);
        $company = $this->resolveCompany($data, overwrite: true);

        $hash = $this->hashFor($company->id, $data);
        if (Opportunity::where('source_hash', $hash)->where('id', '!=', $opportunity->id)->exists()) {
            return back()->withInput()->withErrors(['title' => 'Another opportunity already uses this company and source/title.']);
        }

        $opportunity->update($this->opportunityAttributes($data) + [
            'company_id' => $company->id,
            'source_hash' => $hash,
        ]);
        $this->syncServices($opportunity, $data);
        ActivityLog::record($opportunity, 'updated', 'Details edited.');

        return redirect()->route('opportunities.show', $opportunity)->with('status', 'Opportunity updated.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->delete();

        return redirect()->route('opportunities.index')->with('status', 'Opportunity deleted.');
    }

    /** Move through the pipeline. */
    public function status(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('opportunity.statuses')))],
            'rejection_reason' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('status') === 'rejected')],
        ]);

        $from = $opportunity->status;
        $opportunity->status = $data['status'];
        $opportunity->rejection_reason = $data['status'] === 'rejected' ? $data['rejection_reason'] : null;
        if ($data['status'] === 'qualified' && ! $opportunity->qualified_at) {
            $opportunity->qualified_at = now();
        }
        if ($data['status'] === 'contacted' && ! $opportunity->contacted_at) {
            $opportunity->contacted_at = now();
        }
        $opportunity->save();

        // Priority depends on status (rejected has none), so refresh it when scores exist.
        if ($opportunity->scores()->exists()) {
            $opportunity->recalculateScore();
        } elseif ($data['status'] === 'rejected') {
            $opportunity->update(['priority' => null]);
        }

        ActivityLog::record($opportunity, 'status_changed', "Status: {$from} → {$data['status']}", [
            'from' => $from, 'to' => $data['status'], 'reason' => $opportunity->rejection_reason,
        ]);

        return back()->with('status', 'Status updated.');
    }

    /** Manual scoring + recommended action/channel. */
    public function assess(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $criteria = array_keys(config('opportunity.criteria'));
        $data = $request->validate([
            'scores' => ['nullable', 'array'],
            'scores.*.score' => ['nullable', 'integer', 'between:0,100'],
            'scores.*.reasoning' => ['nullable', 'string', 'max:1000'],
            'confidence' => ['nullable', Rule::in(['low', 'moderate', 'high'])],
            'recommended_action' => ['nullable', 'string', 'max:2000'],
            'recommended_channel' => ['nullable', Rule::in(array_keys(config('opportunity.channels')))],
            'channel_rationale' => ['nullable', 'string', 'max:2000'],
        ]);

        $weights = Setting::get('score_weights', config('opportunity.default_weights'));

        foreach ($criteria as $criterion) {
            $row = $data['scores'][$criterion] ?? [];
            if (($row['score'] ?? '') === '' || ! isset($row['score'])) {
                OpportunityScore::where('opportunity_id', $opportunity->id)->where('criterion', $criterion)->delete();

                continue;
            }
            OpportunityScore::updateOrCreate(
                ['opportunity_id' => $opportunity->id, 'criterion' => $criterion],
                ['score' => (int) $row['score'], 'weight' => (int) ($weights[$criterion] ?? 0), 'reasoning' => $row['reasoning'] ?? null]
            );
        }

        $opportunity->fill([
            'confidence' => $data['confidence'] ?? null,
            'recommended_action' => $data['recommended_action'] ?? null,
            'recommended_channel' => $data['recommended_channel'] ?? null,
            'channel_rationale' => $data['channel_rationale'] ?? null,
        ])->save();

        if ($opportunity->scores()->exists()) {
            $opportunity->recalculateScore();
        } else {
            $opportunity->update(['total_score' => null, 'priority' => null]);
        }
        ActivityLog::record($opportunity, 'assessed', 'Assessment saved (score '.($opportunity->total_score ?? 'n/a').').');

        return back()->with('status', 'Assessment saved.');
    }

    // ---------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'size_band' => ['nullable', Rule::in(['1-10', '11-50', '51-200', '201-1000', '1000+'])],

            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(config('opportunity.types')))],
            'source_url' => ['nullable', 'url', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'business_need' => ['nullable', 'string', 'max:5000'],
            'inferred_opportunity' => ['nullable', 'string', 'max:5000'],
            'engagement_type' => ['nullable', Rule::in(array_keys(config('opportunity.engagements')))],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0', 'gte:budget_min'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
            'budget_is_verified' => ['nullable', 'boolean'],
            'posted_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'primary_service_id' => ['nullable', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['exists:services,id'],
        ]);
    }

    private function opportunityAttributes(array $d): array
    {
        return [
            'title' => $d['title'],
            'type' => $d['type'],
            'source_url' => $d['source_url'] ?? null,
            'summary' => $d['summary'] ?? null,
            'business_need' => $d['business_need'] ?? null,
            'inferred_opportunity' => $d['inferred_opportunity'] ?? null,
            'engagement_type' => $d['engagement_type'] ?? null,
            'budget_min' => $d['budget_min'] ?? null,
            'budget_max' => $d['budget_max'] ?? null,
            'budget_currency' => isset($d['budget_currency']) ? strtoupper($d['budget_currency']) : null,
            'budget_is_verified' => (bool) ($d['budget_is_verified'] ?? false),
            'posted_at' => $d['posted_at'] ?? null,
            'expires_at' => $d['expires_at'] ?? null,
            'primary_service_id' => $d['primary_service_id'] ?? null,
        ];
    }

    private function hashFor(int $companyId, array $d): string
    {
        return sha1($companyId.'|'.(($d['source_url'] ?? null) ?: mb_strtolower($d['title'])));
    }

    /** Find by domain, then by name; otherwise create. Dedupes companies. */
    private function resolveCompany(array $d, bool $overwrite): Company
    {
        $attrs = array_filter([
            'name' => $d['company_name'],
            'website_url' => $d['website_url'] ?? null,
            'industry' => $d['industry'] ?? null,
            'country' => isset($d['country']) ? strtoupper($d['country']) : null,
            'city' => $d['city'] ?? null,
            'size_band' => $d['size_band'] ?? null,
        ], fn ($v) => filled($v));

        $domain = Company::normalizeDomain($d['website_url'] ?? null);
        $company = $domain ? Company::where('domain', $domain)->first() : null;
        $company ??= Company::whereRaw('LOWER(name) = ?', [mb_strtolower($d['company_name'])])->first();

        if (! $company) {
            return Company::create($attrs);
        }

        foreach ($attrs as $key => $value) {
            if ($overwrite || blank($company->{$key})) {
                $company->{$key} = $value;
            }
        }
        if ($overwrite && ! empty($attrs['website_url'])) {
            $company->domain = $domain;
        }
        $company->save();

        return $company;
    }

    /** Primary service first; extra matches kept; existing match strength preserved. */
    private function syncServices(Opportunity $opportunity, array $d): void
    {
        $primary = $d['primary_service_id'] ?? null;
        $ids = array_values(array_unique(array_filter(array_merge([$primary], $d['service_ids'] ?? []))));
        $existing = $opportunity->services()->get()->keyBy('id');

        $sync = [];
        foreach ($ids as $id) {
            $sync[$id] = [
                'is_primary' => (int) $id === (int) $primary,
                'match_strength' => $existing[$id]->pivot->match_strength ?? 0,
                'rationale' => $existing[$id]->pivot->rationale ?? null,
            ];
        }
        $opportunity->services()->sync($sync);
    }
}
