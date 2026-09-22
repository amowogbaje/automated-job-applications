<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadSearch;
use App\Services\Leads\HunterClient;
use Illuminate\Http\Request;

class LeadsController extends Controller
{
    // Broad-first-then-narrow order: each of these three covers a whole
    // business region in one call (using match=any across every stack),
    // so the first pass costs 3 calls, not 3 regions × 5 stacks = 15.
    // Spend the rest of the monthly budget drilling into whichever of
    // these turns up the most promising results_count.
    private const SUGGESTED_SWEEPS = [
        ['label' => 'Americas — all stacks', 'regions' => ['AMER'], 'stacks' => ['nestjs', 'fastapi', 'golang', 'nodejs', 'laravel']],
        ['label' => 'Europe/UK/Ireland/South Africa — all stacks', 'regions' => ['EMEA'], 'stacks' => ['nestjs', 'fastapi', 'golang', 'nodejs', 'laravel']],
        ['label' => 'Asia-Pacific — all stacks', 'regions' => ['APAC'], 'stacks' => ['nestjs', 'fastapi', 'golang', 'nodejs', 'laravel']],
    ];

    // GET /leads — the pipeline board.
    public function index(Request $request)
    {
        $leads = Lead::ownedBy($request->user()->id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'status' => $request->query('status', 'all'),
            'hunterConfigured' => app(HunterClient::class)->isConfigured(),
        ]);
    }

    // GET /leads/discover — the search form.
    public function showDiscoverForm(Request $request)
    {
        $used = LeadSearch::usedThisMonth($request->user()->id);
        $doneSweeps = LeadSearch::where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->pluck('regions')
            ->flatten()
            ->filter()
            ->unique()
            ->all();

        return view('leads.discover', [
            'hunterConfigured' => app(HunterClient::class)->isConfigured(),
            'used' => $used,
            'limit' => LeadSearch::FREE_TIER_MONTHLY_LIMIT,
            'suggestedSweeps' => collect(self::SUGGESTED_SWEEPS)->reject(
                fn ($sweep) => in_array($sweep['regions'][0], $doneSweeps)
            )->values(),
            'recentSearches' => LeadSearch::where('user_id', $request->user()->id)->latest()->limit(10)->get(),
        ]);
    }

    // POST /leads/discover — calls Hunter's free Discover endpoint and
    // stores each result as a new lead (status "new"), no contact info yet.
    public function discover(Request $request, HunterClient $hunter)
    {
        if (! $hunter->isConfigured()) {
            return back()->withErrors(['query' => 'Set HUNTER_API_KEY in .env first — get a free key at hunter.io.']);
        }

        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:500'],
            'countries' => ['nullable', 'array'],
            'regions' => ['nullable', 'array'],
            'stacks' => ['nullable', 'array'],
        ]);

        if (empty($data['query']) && empty($data['countries']) && empty($data['regions']) && empty($data['stacks'])) {
            return back()->withErrors(['query' => 'Describe what you\'re looking for, or pick at least one filter below.']);
        }

        $used = LeadSearch::usedThisMonth($request->user()->id);
        if ($used >= LeadSearch::FREE_TIER_MONTHLY_LIMIT) {
            return back()->withErrors(['query' => "You've used all {$used} of your ".LeadSearch::FREE_TIER_MONTHLY_LIMIT." free Discover calls for this month. Wait for it to reset, or upgrade at hunter.io."]);
        }

        $keywordMap = [
            'nestjs' => 'NestJS backend',
            'fastapi' => 'FastAPI backend',
            'golang' => 'Go backend',
            'laravel' => 'Laravel backend',
            'nodejs' => 'Node.js backend',
        ];
        $keywords = collect($data['stacks'] ?? [])->map(fn ($s) => $keywordMap[$s] ?? $s)->all();

        try {
            $companies = $hunter->discover([
                'query' => $data['query'] ?? null,
                'countries' => $data['countries'] ?? [],
                'regions' => $data['regions'] ?? [],
                'keywords' => $keywords,
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['query' => $e->getMessage()]);
        }

        $created = 0;
        foreach ($companies as $company) {
            if (empty($company['domain'])) {
                continue;
            }

            $lead = Lead::firstOrNew([
                'user_id' => $request->user()->id,
                'domain' => $company['domain'],
            ]);

            if (! $lead->exists) {
                $lead->fill([
                    'company_name' => $company['organization'] ?? $company['domain'],
                    'source' => 'hunter_discover',
                    'status' => 'new',
                    'tech_stack' => $data['stacks'] ?? [],
                ])->save();
                $created++;
            }
        }

        LeadSearch::create([
            'user_id' => $request->user()->id,
            'query' => $data['query'] ?? null,
            'stacks' => $data['stacks'] ?? [],
            'countries' => $data['countries'] ?? [],
            'regions' => $data['regions'] ?? [],
            'results_count' => count($companies),
            'new_leads_count' => $created,
        ]);

        $remaining = LeadSearch::FREE_TIER_MONTHLY_LIMIT - $used - 1;

        return redirect()->route('leads.index')->with('status', "Found " . count($companies) . " companies, added {$created} new leads (duplicates skipped). {$remaining} Discover calls left this month.");
    }

    // POST /leads/{lead}/reveal — spends Hunter credits to find one
    // decision-maker/technical contact for this specific lead.
    public function reveal(Lead $lead, HunterClient $hunter)
    {
        $this->authorizeOwner($lead);

        if (! $lead->domain) {
            return back()->withErrors(['lead' => 'This lead has no domain to search — add one first or enter a contact manually.']);
        }

        try {
            $contact = $hunter->findDecisionMakerContact($lead->domain);
        } catch (\Throwable $e) {
            return back()->withErrors(['lead' => $e->getMessage()]);
        }

        if (! $contact) {
            return back()->with('status', 'No decision-maker contact found for this domain — try adding one manually.');
        }

        $lead->update([
            'contact_name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?: null,
            'contact_email' => $contact['value'] ?? null,
            'contact_position' => $contact['position'] ?? null,
        ]);

        return back()->with('status', "Found a contact: {$lead->contact_email}");
    }

    // POST /leads — add one manually (a company you found yourself, no API involved).
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Lead::create($data + [
            'user_id' => $request->user()->id,
            'source' => 'manual',
            'status' => 'new',
        ]);

        return redirect()->route('leads.index')->with('status', 'Lead added.');
    }

    // PATCH /leads/{lead} — update status/notes as you work the pipeline.
    public function update(Request $request, Lead $lead)
    {
        $this->authorizeOwner($lead);

        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,replied,won,lost'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead->update($data + ($data['status'] === 'contacted' && ! $lead->contacted_at ? ['contacted_at' => now()] : []));

        return back()->with('status', 'Updated.');
    }

    // DELETE /leads/{lead}
    public function destroy(Lead $lead)
    {
        $this->authorizeOwner($lead);
        $lead->delete();

        return back()->with('status', 'Lead removed.');
    }

    private function authorizeOwner(Lead $lead): void
    {
        abort_unless($lead->user_id === request()->user()->id, 403);
    }
}
