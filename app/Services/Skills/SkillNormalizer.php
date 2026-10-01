<?php

namespace App\Services\Skills;

/**
 * Turns whatever a CV, an AI parse, or a form field hands us into a clean
 * list of ATOMIC, canonically-named skills.
 *
 *   "Node.js (NestJS, Express.js)"          -> Node.js, NestJS, Express.js
 *   "PHP 8 (OOP, MVC)"                      -> PHP, OOP, MVC
 *   "Go (production)"                       -> Go            (qualifier: production)
 *   "Python/FastAPI (personal-project level)"-> Python, FastAPI (qualifier on both)
 *   "nest js", "NestJs", "Nest.js"          -> NestJS
 *   "NestJs, graphql, microservices"        -> NestJS, GraphQL, Microservices
 *
 * The catalog lives in config/skills.php. Unknown skills are preserved as
 * typed (deduped by a compact key) — the catalog improves consistency and
 * matching; it never drops a skill.
 *
 * Pure PHP on purpose (catalog injected through the constructor) so it can be
 * unit-tested without booting Laravel.
 */
class SkillNormalizer
{
    /** Words that mark a parenthetical as a level/experience note, not a skill. */
    private const QUALIFIER_WORDS = [
        'production', 'prod', 'personal', 'project', 'level', 'basic', 'basics', 'beginner',
        'intermediate', 'advanced', 'expert', 'proficient', 'proficiency', 'familiar',
        'familiarity', 'working', 'knowledge', 'exposure', 'hands-on', 'hands', 'on',
        'years', 'year', 'yrs', 'yr', 'experience', 'learning', 'currently', 'some', 'strong',
        'fluent', 'native', 'professional', 'commercial', 'side', 'hobby', 'academic',
    ];

    /** @var array<string, array{name:string, slug:string, category:string, aliases:string[], text_aliases:string[], case_sensitive:bool}> */
    private array $skills = [];

    /** @var array<string, string> compact key => slug */
    private array $index = [];

    public function __construct(?array $catalog = null)
    {
        foreach (($catalog ?? $this->defaultCatalog()) as $name => $def) {
            $slug = self::compact($name);
            $aliases = [];
            $textAliases = [];

            foreach (($def[1] ?? []) as $alias) {
                $inputOnly = str_starts_with($alias, '~');
                $alias = ltrim($alias, '~');
                $aliases[] = $alias;
                if (! $inputOnly) {
                    $textAliases[] = $alias;
                }
            }

            $this->skills[$slug] = [
                'name' => $name,
                'slug' => $slug,
                'category' => $def[0] ?? 'other',
                'aliases' => $aliases,
                'text_aliases' => $textAliases,
                'case_sensitive' => (bool) (($def[2]['case_sensitive'] ?? false)),
            ];

            $this->index[$slug] = $slug;
            foreach ($aliases as $alias) {
                $this->index[self::compact($alias)] ??= $slug;
            }
        }
    }

    private function defaultCatalog(): array
    {
        if (function_exists('config')) {
            try {
                $c = config('skills');
                if (is_array($c)) {
                    return $c;
                }
            } catch (\Throwable) {
                // fall through to the file
            }
        }

        return require dirname(__DIR__, 3) . '/config/skills.php';
    }

    /**
     * Normalize one raw string or a list of them.
     *
     * @param  string|array<int, string>  $raw
     * @return array<int, array{name:string, slug:string, category:?string, qualifier:?string, known:bool}>
     */
    public function normalize(string|array $raw): array
    {
        $out = [];

        foreach ((array) $raw as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }

            foreach ($this->explode($item) as $skill) {
                $out[$skill['slug']] ??= $skill;
                // Keep a qualifier if a later duplicate carried one.
                if ($out[$skill['slug']]['qualifier'] === null && $skill['qualifier'] !== null) {
                    $out[$skill['slug']]['qualifier'] = $skill['qualifier'];
                }
            }
        }

        return array_values($out);
    }

    /** Just the canonical display names, deduped, in input order. */
    public function names(string|array $raw): array
    {
        return array_column($this->normalize($raw), 'name');
    }

    /** The catalog category for a skill name, or null if it isn't in the catalog. */
    public function categoryFor(string $name): ?string
    {
        $slug = $this->index[self::compact($name)] ?? null;

        return $slug ? $this->skills[$slug]['category'] : null;
    }

    /**
     * Does this job text mention the skill (under any known spelling)?
     * Boundary-aware, so "go" doesn't hit "google" and "java" doesn't hit
     * "javascript". Works for unknown skills too (plain boundary match).
     */
    public function mentionedIn(string $text, string $skill): bool
    {
        foreach ($this->normalize($skill) ?: [['name' => $skill, 'slug' => self::compact($skill)]] as $s) {
            if ($this->mentionsOne($text, $s['slug'], $s['name'])) {
                return true;
            }
        }

        return false;
    }

    /** All skills (canonical names) from a list that the text mentions. */
    public function mentionedSkills(string $text, array $skills): array
    {
        return array_values(array_filter($skills, fn ($s) => $this->mentionedIn($text, $s)));
    }

    // ------------------------------------------------------------------

    private function mentionsOne(string $text, string $slug, string $fallbackName): bool
    {
        $def = $this->skills[$slug] ?? null;

        if (! $def) {
            return $this->boundaryMatch($text, $this->variants($fallbackName), false);
        }

        // Case-sensitive skills (Go): the display name must match exactly as
        // written; aliases (golang) match any case.
        if ($def['case_sensitive'] && $this->boundaryMatch($text, [$def['name']], true)) {
            return true;
        }

        $terms = $def['case_sensitive']
            ? $def['text_aliases']
            : array_merge($this->variants($def['name']), $def['text_aliases']);

        return $this->boundaryMatch($text, $terms, false);
    }

    /** "Node.js" -> node.js, nodejs, node js ; "CI/CD" -> ci/cd, cicd, ci cd */
    private function variants(string $name): array
    {
        $n = strtolower(trim($name));

        return array_values(array_unique(array_filter([
            $n,
            str_replace(['.', '/'], '', $n),
            str_replace(['.', '/', '-'], ' ', $n),
            str_replace(['-'], '', $n),
        ])));
    }

    private function boundaryMatch(string $text, array $terms, bool $caseSensitive): bool
    {
        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }
            // Boundary = not adjacent to a letter/digit; '+' and '#' count as
            // part of a word so "c" never matches "c++" or "c#".
            $pattern = '/(?<![a-z0-9+#])' . preg_quote($term, '/') . '(?![a-z0-9+#])/u' . ($caseSensitive ? '' : 'i');
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{name:string, slug:string, category:?string, qualifier:?string, known:bool}> */
    private function explode(string $item): array
    {
        $item = trim(preg_replace('/\s+/', ' ', $item));

        // 1. The whole string is already a known skill ("CI/CD", "Vue.js").
        if ($hit = $this->lookup($item)) {
            return [$this->make($hit, null)];
        }

        // 2. Split a "Base (inner, inner)" shape into base + inner parts.
        $base = $item;
        $inner = '';
        if (preg_match_all('/\(([^)]*)\)/', $item, $m)) {
            $inner = implode(',', $m[1]);
            $base = trim(preg_replace('/\s+/', ' ', preg_replace('/\([^)]*\)/', ' ', $item)));
        }

        $results = [];
        $qualifier = null;

        foreach ($this->splitList($inner) as $part) {
            if ($this->isQualifier($part)) {
                $qualifier = $qualifier ? $qualifier . ', ' . $part : $part;
                continue;
            }
            foreach ($this->atoms($part) as $atom) {
                $results[] = $this->make($atom, null);
            }
        }

        // 3. The base itself may still be a list ("Python/FastAPI", "React, Vue").
        $baseSkills = [];
        foreach ($this->atoms($base) as $atom) {
            $baseSkills[] = $this->make($atom, $qualifier);
        }

        return array_merge($baseSkills, $results);
    }

    /** Resolve one fragment to atoms: a known skill, several (split on / , & +), or an unknown as-typed. */
    private function atoms(string $fragment): array
    {
        $fragment = trim($fragment, " \t\n\r\0\x0B-–—:;");
        if ($fragment === '') {
            return [];
        }

        if ($hit = $this->lookup($fragment)) {
            return [$hit];
        }

        $pieces = preg_split('/\s*(?:\/|,|;|&)\s*|\s+\+\s+|\s+(?:and|or)\s+/i', $fragment) ?: [$fragment];

        if (count($pieces) > 1) {
            $atoms = [];
            foreach ($pieces as $piece) {
                foreach ($this->atoms($piece) as $a) {
                    $atoms[] = $a;
                }
            }

            return $atoms;
        }

        return [['slug' => null, 'name' => $fragment]]; // unknown, keep as typed
    }

    /** Known-skill lookup, including a trailing version number ("PHP 8", "Vue 3", "Python 3.11"). */
    private function lookup(string $text): ?array
    {
        $key = self::compact($text);
        if ($key !== '' && isset($this->index[$key])) {
            return ['slug' => $this->index[$key], 'name' => $this->skills[$this->index[$key]]['name']];
        }

        $stripped = preg_replace('/[\s-]+v?\d+(\.\d+)*\+?$/i', '', $text);
        if ($stripped !== $text && $stripped !== '') {
            $key = self::compact($stripped);
            if (isset($this->index[$key])) {
                return ['slug' => $this->index[$key], 'name' => $this->skills[$this->index[$key]]['name']];
            }
        }

        return null;
    }

    private function make(array $atom, ?string $qualifier): array
    {
        if ($atom['slug'] !== null) {
            $def = $this->skills[$atom['slug']];

            return [
                'name' => $def['name'],
                'slug' => $def['slug'],
                'category' => $def['category'],
                'qualifier' => $qualifier,
                'known' => true,
            ];
        }

        $name = $atom['name'];

        return [
            'name' => $name,
            'slug' => self::compact($name) ?: strtolower($name),
            'category' => null,
            'qualifier' => $qualifier,
            'known' => false,
        ];
    }

    private function splitList(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*[,;]\s*/', $s) ?: [])));
    }

    /** A parenthetical is a level note only if it isn't a skill and every word is a qualifier word. */
    private function isQualifier(string $part): bool
    {
        if ($this->lookup($part)) {
            return false;
        }

        $words = preg_split('/[\s\-\/]+/', strtolower($part), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return false;
        }

        foreach ($words as $w) {
            if (! in_array($w, self::QUALIFIER_WORDS, true) && ! preg_match('/^\d+\+?$/', $w)) {
                return false;
            }
        }

        return true;
    }

    /** Lowercase, keep only letters/digits/+/#: "Node.js" -> "nodejs", "C++" -> "c++", "CI/CD" -> "cicd". */
    public static function compact(string $s): string
    {
        return preg_replace('/[^a-z0-9+#]/', '', strtolower($s)) ?? '';
    }
}
