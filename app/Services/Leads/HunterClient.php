<?php

namespace App\Services\Leads;

use Illuminate\Support\Facades\Http;

class HunterClient
{
    private string $key;

    public function __construct()
    {
        $this->key = config('services.hunter.key');
    }

    public function isConfigured(): bool
    {
        return filled($this->key);
    }

    /**
     * Free — returns up to 100 companies matching the criteria. Does not
     * reveal any contact info, just which companies exist and roughly
     * how many email addresses Hunter has on file for each.
     *
     * @param array{query?:string, countries?:string[], regions?:string[], keywords?:string[], industries?:string[], headcount?:string[]} $criteria
     */
    public function discover(array $criteria): array
    {
        $body = [];

        if (! empty($criteria['query'])) {
            $body['query'] = $criteria['query'];
        }

        // Mixing individual countries with broad business_region groups
        // (AMER/EMEA/APAC/LATAM) in one call is what makes a single
        // Discover call cover far more ground than one country at a
        // time — a region entry and a country entry can sit side by
        // side in the same include list.
        $locations = array_map(fn ($c) => ['country' => $c], $criteria['countries'] ?? []);
        $locations = array_merge($locations, array_map(fn ($r) => ['business_region' => $r], $criteria['regions'] ?? []));

        if (! empty($locations)) {
            $body['headquarters_location'] = ['include' => $locations];
        }

        if (! empty($criteria['keywords'])) {
            $body['keywords'] = ['include' => $criteria['keywords'], 'match' => 'any'];
        }

        if (! empty($criteria['industries'])) {
            $body['industry'] = ['include' => $criteria['industries']];
        }

        if (! empty($criteria['headcount'])) {
            $body['headcount'] = $criteria['headcount'];
        }

        $response = Http::post('https://api.hunter.io/v2/discover?api_key=' . $this->key, $body);

        if ($response->failed()) {
            throw new \RuntimeException($response->json('errors.0.details') ?? 'Hunter Discover request failed.');
        }

        return $response->json('data', []);
    }

    /**
     * Costs Hunter credits (1 credit per email revealed). Only ever call
     * this for a lead someone has explicitly chosen to reveal — never as
     * part of bulk discovery, to keep credit spend and outreach volume
     * deliberate rather than automatic.
     *
     * Restricted to decision-maker / technical roles so what comes back
     * is someone plausibly able to say yes to hiring a developer, not an
     * arbitrary employee at the company.
     */
    public function findDecisionMakerContact(string $domain): ?array
    {
        $response = Http::get('https://api.hunter.io/v2/domain-search', [
            'domain' => $domain,
            'api_key' => $this->key,
            'department' => 'executive,it,management',
            'decision_maker' => 'true',
            'limit' => 3,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException($response->json('errors.0.details') ?? 'Hunter Domain Search request failed.');
        }

        $emails = $response->json('data.emails', []);

        return $emails[0] ?? null;
    }
}
