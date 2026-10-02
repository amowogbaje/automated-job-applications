<?php

namespace App\Services\Social;

use App\Models\CareerProfile;
use App\Services\Skills\SkillNormalizer;

/** Which skills to search social platforms for: the CORE stack of the accounts on this install. */
class SkillTags
{
    /** @return string[] canonical skill names, most common across accounts first, generic skills excluded */
    public static function coreSkills(int $limit = 12): array
    {
        $normalizer = app(SkillNormalizer::class);
        $counts = [];

        $profiles = CareerProfile::query()->where('social_jobs_enabled', true)->get(['required_skills', 'keywords']);
        foreach ($profiles as $p) {
            $skills = $normalizer->names(array_merge((array) ($p->required_skills ?? []), (array) ($p->keywords ?? [])));
            foreach ($skills as $s) {
                if (! $normalizer->isGeneric($s)) {
                    $counts[$s] = ($counts[$s] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, $limit);
    }

    /** "Node.js" -> nodejs, "C++" -> cpp, "Vue.js" -> vuejs (hashtag form). */
    public static function hashtag(string $skill): string
    {
        $t = strtolower(str_replace(['C++', 'c++'], 'cpp', $skill));
        $t = str_replace('c#', 'csharp', $t);

        return preg_replace('/[^a-z0-9]/', '', $t) ?? '';
    }
}
