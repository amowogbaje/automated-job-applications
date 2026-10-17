<?php

namespace App\Services\Agent\Research;

class RobotsTxt
{
    /** Minimal robots.txt check: our agent's group if present, else "*". Longest matching rule wins; Allow wins ties. */
    public static function allows(string $robots, string $path, string $agent = 'opportunityagentbot'): bool
    {
        $groups = [];   // each: ['agents' => [...], 'rules' => [[type, pattern], ...]]
        $idx = null;
        $lastWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $robots) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $k = strtolower($k);

            if ($k === 'user-agent') {
                if (! $lastWasAgent || $idx === null) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $idx = array_key_last($groups);
                }
                $groups[$idx]['agents'][] = strtolower($v);
                $lastWasAgent = true;
            } elseif (in_array($k, ['allow', 'disallow'], true) && $idx !== null) {
                $groups[$idx]['rules'][] = [$k, $v];
                $lastWasAgent = false;
            } else {
                $lastWasAgent = false;
            }
        }

        $rules = null;
        foreach ($groups as $g) {
            foreach ($g['agents'] ?? [] as $a) {
                if ($a !== '*' && str_contains($agent, $a)) {
                    $rules = $g['rules'] ?? [];
                    break 2;
                }
            }
        }
        if ($rules === null) {
            foreach ($groups as $g) {
                if (in_array('*', $g['agents'] ?? [], true)) {
                    $rules = $g['rules'] ?? [];
                    break;
                }
            }
        }
        if (! $rules) {
            return true;
        }

        $best = null;
        $bestLen = -1;
        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '') {
                continue; // "Disallow:" with no value allows everything
            }
            if (self::matches($pattern, $path)) {
                $len = strlen($pattern);
                if ($len > $bestLen || ($len === $bestLen && $type === 'allow')) {
                    $best = $type;
                    $bestLen = $len;
                }
            }
        }

        return $best !== 'disallow';
    }

    private static function matches(string $pattern, string $path): bool
    {
        $regex = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')).'#';

        return (bool) preg_match($regex, $path);
    }
}
