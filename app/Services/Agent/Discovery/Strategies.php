<?php

namespace App\Services\Agent\Discovery;

use App\Models\Service;

/**
 * The five discovery strategies from the plan. Each one only decides WHAT to search for (query
 * templates in config/discovery.php) and what kind of evidence/opportunity a hit usually is.
 * Everything found is a candidate, never a conclusion: the research agent qualifies it later.
 */
class Strategies
{
    public const ALL = [
        'technical_need' => [
            'label' => 'Technical need',
            'about' => 'Businesses showing public signs they could use software or automation.',
            'type' => 'inferred_need', 'evidence' => 'other',
            'hint' => 'A public sign of a technical need (a stated problem, a manual process they describe, a new digital product). An old website alone is NOT a need.',
        ],
        'hiring' => [
            'label' => 'Companies hiring',
            'about' => 'Open developer roles or contracts that match your service.',
            'type' => 'job_posting', 'evidence' => 'job_listing',
            'hint' => 'An open technical vacancy or contract. A permanent role does not mean they accept freelancers.',
        ],
        'external_assistance' => [
            'label' => 'Seeking outside help',
            'about' => 'Requests for proposals, project briefs, partner or contractor requests.',
            'type' => 'rfp', 'evidence' => 'rfp',
            'hint' => 'A public request for outside technical help (RFP, brief, contractor or partner request).',
        ],
        'funded_growth' => [
            'label' => 'Funded or growing',
            'about' => 'Announced funding, expansion or new products (a hint of demand, not of budget).',
            'type' => 'growth_signal', 'evidence' => 'funding',
            'hint' => 'An announcement of funding, expansion or a launch. This is a weak signal: it does not prove a budget for outside developers.',
        ],
        'portfolio_fit' => [
            'label' => 'Portfolio fit',
            'about' => 'Companies in industries where your existing projects are proof of expertise.',
            'type' => 'inferred_need', 'evidence' => 'other',
            'hint' => 'A company in an industry where the described portfolio project would be directly relevant.',
        ],
    ];

    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function exists(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    public static function get(string $key): array
    {
        return self::ALL[$key] ?? self::ALL['technical_need'];
    }

    /**
     * Build the search queries for one strategy and service.
     *
     * @param  string[]  $extra  hand-written queries from the person; used first
     * @return string[]
     */
    public static function queries(string $key, ?Service $service, array $extra = [], ?int $limit = null): array
    {
        $limit ??= (int) self::cfg('max_queries_per_run', 4);
        $out = [];
        foreach ($extra as $q) {
            if (($q = trim((string) $q)) !== '') {
                $out[] = $q;
            }
        }

        if ($service) {
            foreach (self::expand((array) self::cfg("templates.{$key}", []), self::vocab($key, $service)) as $q) {
                $out[] = $q;
            }
        }

        $seen = [];
        $final = [];
        foreach ($out as $q) {
            $k = mb_strtolower($q);
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $final[] = $q;
            }
        }

        return array_slice($final, 0, max(1, $limit));
    }

    /** @return array<string, string[]> placeholder => candidate values */
    public static function vocab(string $key, Service $service): array
    {
        $take = fn ($v, int $n = 2) => array_slice(array_values(array_filter(array_map('trim', (array) $v))), 0, $n);
        $vocab = [
            'keyword' => $take($service->search_keywords),
            'skill' => $take($service->skills_required),
            'target' => $take($service->target_businesses),
            'signal' => $take($service->opportunity_signals),
            'region' => $take($service->geographic_preferences, 1) ?: [''],
        ];

        if ($key === 'portfolio_fit') {
            $projects = $service->portfolioProjects()->get();
            $vocab['industry'] = $take($projects->flatMap(fn ($p) => (array) $p->industries)->unique()->all(), 3);
            $vocab['technology'] = $take($projects->flatMap(fn ($p) => (array) $p->technologies)->unique()->all(), 2);
        }

        return $vocab;
    }

    /**
     * Fill each template with values, rotating through them so one run uses varied queries. Templates
     * that name a placeholder without a value are skipped.
     *
     * @param  string[]  $templates
     * @param  array<string, string[]>  $vocab
     * @return string[]
     */
    public static function expand(array $templates, array $vocab): array
    {
        $out = [];
        for ($round = 0; $round < 2; $round++) {
            foreach ($templates as $t) {
                preg_match_all('/\{(\w+)\}/', $t, $m);
                $filled = $t;
                $ok = true;
                foreach (array_unique($m[1]) as $name) {
                    $values = $vocab[$name] ?? [];
                    if ($values === []) {
                        $ok = false;
                        break;
                    }
                    $filled = str_replace('{'.$name.'}', (string) $values[$round % count($values)], $filled);
                }
                if ($ok) {
                    $out[] = trim(preg_replace('/\s+/', ' ', $filled));
                }
            }
        }

        return $out;
    }

    /** config('discovery.*'); outside the framework (plain unit tests) the default is used. */
    private static function cfg(string $key, mixed $default): mixed
    {
        return function_exists('config') ? config('discovery.'.$key, $default) : $default;
    }
}
