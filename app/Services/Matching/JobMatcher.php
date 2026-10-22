<?php

namespace App\Services\Matching;

use App\Services\Skills\SkillNormalizer;

/**
 * Scores one job against one career profile. Pure PHP (no Eloquent) so it is
 * unit-testable; CareerProfile::score() just feeds it the profile's fields.
 *
 * What changed vs the old substring matcher (and why):
 *  1. Excluded words are boundary-aware: "intern" no longer kills "international".
 *  2. Generic skills (Git, REST, SQL, Agile...) can't qualify a job alone. Importing
 *     a whole resume used to make EVERY skill "required" with min 1, so a Java job
 *     mentioning Git or REST "matched".
 *  3. A skill in the TITLE outweighs one buried in the description.
 *  4. Core required skills outweigh nice-to-haves; generic hits add at most 3.
 *  5. Result carries a tier (strong/good/weak) so the UI can say how good a match
 *     is instead of a bare number, and `warnings` for things that need a look.
 *  6. Region restrictions ("US only", "must reside in the EU") are detected; the
 *     profile can hide them. Remote != "remote from anywhere".
 */
class JobMatcher
{
    public const GENERIC_SCORE_CAP = 3;

    /** Phrases that usually mean a "remote" role is limited to certain countries. */
    private const REGION_PATTERNS = [
        '/\b(?:u\.?s\.?a?|united states|uk|u\.k\.|canada|eu|europe)\s*(?:only|-only)\b/i',
        '/\b(?:us|u\.s\.|uk|eu|canada)[\s-]based\s+(?:candidates|applicants|only|role|position)\b/i',
        '/\bmust\s+(?:be\s+(?:located|based|resident)|reside|live|work)\s+(?:in|within)\s+(?:the\s+)?(?:u\.?s\.?a?|united states|uk|united kingdom|eu|europe|canada)\b/i',
        '/\b(?:authori[sz]ed|eligible|legally entitled)\s+to\s+work\s+in\s+(?:the\s+)?(?:u\.?s\.?a?|united states|uk|united kingdom|eu|canada)\b/i',
        '/\bwithin\s+(?:the\s+)?(?:continental\s+)?(?:u\.?s\.?a?|united states)\s+(?:time\s*zones?|only)\b/i',
    ];

    public function __construct(private SkillNormalizer $skills) {}

    /**
     * @param  string[]  $required  canonical skill names (or custom terms)
     * @param  string[]  $keywords
     * @param  string[]  $excluded  plain words
     * @return array{matches:bool, score:int, matched:string[], tier:?string, title_hits:string[], generic_hits:string[], warnings:string[], excluded_by:?string}
     */
    public function score(
        string $title,
        ?string $description,
        array $required,
        array $keywords,
        array $excluded = [],
        int $minRequired = 1,
        bool $hideRegionRestricted = false,
        array $workTypes = [],
        array $workLocations = [],
        ?string $location = null,
        bool $isRemote = false,
    ): array {
        $text = $title . ' ' . ($description ?? '');
        $none = ['matches' => false, 'score' => 0, 'matched' => [], 'tier' => null, 'title_hits' => [], 'generic_hits' => [], 'warnings' => [], 'excluded_by' => null];

        foreach ($excluded as $word) {
            $word = trim($word);
            if ($word !== '' && $this->skills->mentionedIn($text, $word)) {
                return ['excluded_by' => $word] + $none;
            }
        }

        if ($workTypes || $workLocations) {
            $mode = WorkMode::detect($title, $description, $location, $isRemote);
            if ($why = WorkMode::rejects($mode, $workTypes, $workLocations, (string) $location, $text)) {
                return ['excluded_by' => $why] + $none;
            }
        }

        $warnings = $this->regionWarnings($text);
        if ($hideRegionRestricted && $warnings) {
            return ['excluded_by' => 'region restriction'] + $none;
        }

        // Canonicalize here too, so callers can pass raw profile values.
        $required = $this->skills->names($this->clean($required));
        $keywords = array_values(array_diff($this->skills->names($this->clean($keywords)), $required));

        $mentions = fn (string $skill): array => [
            'text' => $this->skills->mentionedIn($text, $skill),
            'title' => $this->skills->mentionedIn($title, $skill),
        ];

        $reqHits = [];   // core required
        $niceHits = [];  // core keywords
        $generic = [];   // generic (either list)
        $inTitle = [];

        foreach ($required as $skill) {
            $m = $mentions($skill);
            if (! $m['text']) {
                continue;
            }
            if ($this->skills->isGeneric($skill)) {
                $generic[$skill] = true;
            } else {
                $reqHits[$skill] = $m['title'];
            }
            $m['title'] && $inTitle[] = $skill;
        }

        foreach ($keywords as $skill) {
            $m = $mentions($skill);
            if (! $m['text']) {
                continue;
            }
            if ($this->skills->isGeneric($skill)) {
                $generic[$skill] = true;
            } else {
                $niceHits[$skill] = $m['title'];
            }
            $m['title'] && $inTitle[] = $skill;
        }

        // What counts as "meets the required-skill bar": core required skills.
        // If the user's required list is ALL generic skills, they're the only
        // thing there is to qualify on, so fall back to them.
        $qualifying = array_values(array_filter($required, fn ($s) => ! $this->skills->isGeneric($s)));
        $qualifyingHits = count($reqHits);
        if ($qualifying === [] && $required !== []) {
            $qualifyingHits = count(array_intersect($required, array_keys($generic)));
        }

        if ($required !== [] && $qualifyingHits < max(1, $minRequired)) {
            return $none;
        }

        // No required list: need at least one core keyword (generic alone isn't enough).
        if ($required === [] && $keywords !== [] && $niceHits === []) {
            return $none;
        }

        $score = 0;
        foreach ($reqHits as $inT) {
            $score += $inT ? 5 : 3;
        }
        foreach ($niceHits as $inT) {
            $score += $inT ? 3 : 2;
        }
        $score += min(self::GENERIC_SCORE_CAP, count($generic));

        $coreHits = count($reqHits) + count($niceHits);
        $coreInTitle = count(array_filter($reqHits)) + count(array_filter($niceHits));

        // Someone who only asked for ONE skill ("just Laravel") can never have two core hits, so for
        // them a title hit is the strongest signal there is.
        $configuredCore = count(array_filter(array_merge($required, $keywords), fn ($s) => ! $this->skills->isGeneric($s)));

        $tier = match (true) {
            $coreInTitle >= 1 && ($coreHits >= 2 || $configuredCore <= 1) => 'strong',
            $coreInTitle >= 1 || $coreHits >= 2 => 'good',
            default => 'weak',
        };

        return [
            'matches' => true,
            'score' => $score,
            'matched' => array_values(array_unique(array_merge(array_keys($reqHits), array_keys($niceHits), array_keys($generic)))),
            'tier' => $tier,
            'title_hits' => array_values(array_unique($inTitle)),
            'generic_hits' => array_keys($generic),
            'warnings' => $warnings,
            'excluded_by' => null,
        ];
    }

    /** @return string[] human-readable flags found in the posting */
    public function regionWarnings(string $text): array
    {
        foreach (self::REGION_PATTERNS as $re) {
            if (preg_match($re, $text, $m)) {
                return ['May be restricted to certain countries: "' . trim($m[0]) . '"'];
            }
        }

        return [];
    }

    private function clean(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) $s), $items))));
    }
}
