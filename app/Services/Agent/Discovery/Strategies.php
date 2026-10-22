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
            'dated' => true, 'label' => 'Technical need',
            'about' => 'Businesses showing public signs they could use software or automation.',
            'type' => 'inferred_need', 'evidence' => 'other',
            'hint' => 'A public sign of a technical need (a stated problem, a manual process they describe, a new digital product). An old website alone is NOT a need.',
        ],
        'hiring' => [
            'dated' => true, 'label' => 'Companies hiring',
            'about' => 'Open developer roles or contracts that match your service.',
            'type' => 'job_posting', 'evidence' => 'job_listing',
            'hint' => 'An open technical vacancy or contract. A permanent role does not mean they accept freelancers.',
        ],
        'external_assistance' => [
            'dated' => true, 'label' => 'Seeking outside help',
            'about' => 'Requests for proposals, project briefs, partner or contractor requests.',
            'type' => 'rfp', 'evidence' => 'rfp',
            'hint' => 'A public request for outside technical help (RFP, brief, contractor or partner request).',
        ],
        'funded_growth' => [
            'dated' => true, 'label' => 'Funded or growing',
            'about' => 'Announced funding, expansion or new products (a hint of demand, not of budget).',
            'type' => 'growth_signal', 'evidence' => 'funding',
            'hint' => 'An announcement of funding, expansion or a launch. This is a weak signal: it does not prove a budget for outside developers.',
        ],
        'prospecting' => [
            'dated' => false, 'label' => 'Business prospecting',
            'about' => 'Non-technical organisations in your target sectors, with public ways to reach them. No stated need required; you decide whether to send a proposal. Contact routes are only saved when the search result shows them, so tick "research automatically" to let the research step find more.',
            'type' => 'prospect', 'evidence' => 'directory',
            'hint' => 'A real, non-technology organisation (clinic, school, shop, logistics firm, NGO, church, farm, estate agent and the like) that could plausibly benefit from the service. It does NOT need to say it wants software. Skip software companies, agencies, job boards and directories themselves.',
        ],
        'portfolio_fit' => [
            'dated' => false, 'label' => 'Portfolio fit',
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
    public static function queries(string $key, ?Service $service, array $extra = [], ?int $limit = null, array $platforms = []): array
    {
        $limit ??= $key === 'prospecting' ? (int) self::cfg('prospect_max_queries', 8) : (int) self::cfg('max_queries_per_run', 4);
        $out = [];
        foreach ($extra as $q) {
            if (($q = trim((string) $q)) !== '') {
                $out[] = $q;
            }
        }

        if ($service && $key === 'prospecting') {
            foreach (self::prospectQueries((array) $service->target_businesses, (array) $service->geographic_preferences, $platforms) as $q) {
                $out[] = $q;
            }
        } elseif ($service) {
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

        // A service delivered one way only (e.g. remote) should search for that way.
        $modes = $service ? array_values(array_intersect(['remote', 'hybrid', 'onsite'], (array) $service->work_modes)) : [];
        if (count($modes) === 1 && in_array($key, ['hiring', 'external_assistance'], true)) {
            $word = $modes[0] === 'onsite' ? 'on-site' : $modes[0];
            $final = array_map(fn ($q) => stripos($q, $word) === false ? trim($q.' '.$word) : $q, $final);
        }

        return array_slice($final, 0, max(1, $limit));
    }

    /**
     * One set of queries per target business (barber shops, mechanics, ...), interleaved so that a small
     * query budget still touches every target: plain, then each chosen social platform, then "contact".
     *
     * @param  string[]  $platforms  keys of config('discovery.platforms')
     * @return string[]
     */
    public static function prospectQueries(array $targetBusinesses, array $regions = [], array $platforms = []): array
    {
        $targets = array_slice(array_values(array_filter(array_map('trim', $targetBusinesses))), 0, 6);
        $region = trim((string) (array_values($regions)[0] ?? ''));
        $words = ['instagram' => 'instagram', 'facebook' => 'facebook page', 'linkedin' => 'linkedin', 'x' => 'twitter', 'tiktok' => 'tiktok'];
        $per = [];
        foreach ($targets as $t) {
            $base = trim($t.' '.$region);
            $v = [$base];
            foreach ($platforms as $p) {
                if (isset($words[$p])) {
                    $v[] = $base.' '.$words[$p];
                }
            }
            $v[] = $base.' contact us';
            $per[] = $v;
        }
        $out = [];
        $max = $per ? max(array_map('count', $per)) : 0;
        for ($i = 0; $i < $max; $i++) {
            foreach ($per as $v) {
                if (isset($v[$i])) {
                    $out[] = $v[$i];
                }
            }
        }

        return $out;
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
