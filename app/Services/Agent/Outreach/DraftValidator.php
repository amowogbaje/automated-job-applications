<?php

namespace App\Services\Agent\Outreach;

/**
 * Deterministic checks on what the model returned. The point is integrity: no invented links or contact
 * details, no unsupported claims about the company, three genuinely different drafts, and the length the
 * channel needs. A failure message is fed back to the failover layer, which tries the next provider.
 *
 * $ctx keys: channel, evidence_ids (int[]), projects (id => name), allowed_hosts (string[]),
 *            allowed_contacts (string[] — emails and phone numbers that may appear), has_evidence (bool)
 */
class DraftValidator
{
    /** @return array{ok: bool, error: ?string, drafts: array<string, array>} */
    public static function check(?array $data, array $ctx): array
    {
        $fail = fn (string $m) => ['ok' => false, 'error' => $m, 'drafts' => []];

        if (! $data || ! isset($data['drafts']) || ! is_array($data['drafts'])) {
            return $fail('The reply had no "drafts" list.');
        }

        $spec = OutreachChannels::spec($ctx['channel']);
        $byApproach = [];
        foreach ($data['drafts'] as $d) {
            if (is_array($d) && isset($d['approach']) && in_array($d['approach'], OutreachChannels::APPROACHES, true)) {
                $byApproach[$d['approach']] ??= $d;
            }
        }
        foreach (OutreachChannels::APPROACHES as $a) {
            if (! isset($byApproach[$a])) {
                return $fail("Missing the \"{$a}\" draft.");
            }
        }

        $out = [];
        foreach (OutreachChannels::APPROACHES as $a) {
            $r = self::checkOne($byApproach[$a], $a, $spec, $ctx);
            if (is_string($r)) {
                return $fail("{$a}: {$r}");
            }
            $out[$a] = $r;
        }

        $approaches = OutreachChannels::APPROACHES;
        for ($i = 0; $i < 3; $i++) {
            for ($j = $i + 1; $j < 3; $j++) {
                if (self::similarity($out[$approaches[$i]]['body'], $out[$approaches[$j]]['body']) > 0.6) {
                    return $fail("The {$approaches[$i]} and {$approaches[$j]} drafts are too similar; they must take different angles.");
                }
            }
        }

        return ['ok' => true, 'error' => null, 'drafts' => $out];
    }

    /** @return array|string normalised draft, or an error message */
    private static function checkOne(array $d, string $approach, array $spec, array $ctx): array|string
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", (string) ($d['body'] ?? '')));
        if ($body === '') {
            return 'empty body.';
        }
        $words = self::wordCount($body);
        if ($words > (int) ceil($spec['max_words'] * 1.25)) {
            return "too long ({$words} words; this channel needs about {$spec['max_words']} or fewer).";
        }
        if ($words < 12) {
            return 'too short to be useful.';
        }

        $subject = null;
        $alts = [];
        if ($spec['subject']) {
            $subject = trim((string) ($d['subject'] ?? ''));
            if ($subject === '' || mb_strlen($subject) > 90) {
                return 'needs a subject of 1-90 characters.';
            }
            foreach (array_slice((array) ($d['alt_subjects'] ?? []), 0, 2) as $s) {
                $s = trim((string) $s);
                if ($s !== '' && mb_strlen($s) <= 90) {
                    $alts[] = $s;
                }
            }
        }

        $text = $body."\n".($subject ?? '');

        if (preg_match('/\[(?:your|name|company|insert|first|last|role|title|date)[^\]]{0,30}\]|\{\{|\}\}|<[a-z\/][^>]*>/i', $text)) {
            return 'contains a placeholder or markup that was not filled in.';
        }
        if (preg_match('/\b(guarantee[ds]?|risk[- ]free|100% (?:success|satisf))/i', $text)) {
            return 'makes a promise or guarantee that cannot be backed up.';
        }
        if (preg_match('/hope this (?:email|message|note) finds you/i', $text)) {
            return 'uses a generic opener.';
        }

        // Links: only hosts we supplied (company site, my portfolio projects, my signature).
        preg_match_all('~(?:https?://|www\.)[^\s<>"\')\]]+~i', $text, $m);
        foreach ($m[0] as $url) {
            $host = strtolower((string) parse_url(str_starts_with(strtolower($url), 'www.') ? 'https://'.$url : $url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);
            if (! in_array($host, $ctx['allowed_hosts'], true)) {
                return "contains a link that was not provided ({$host}).";
            }
        }

        // Contact details: emails and phone numbers must be ones we supplied.
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $mails);
        foreach ($mails[0] as $e) {
            if (! in_array(strtolower($e), $ctx['allowed_contacts'], true)) {
                return "contains an email address that was not provided ({$e}).";
            }
        }
        preg_match_all('/\+?\d[\d\s().\-]{6,}\d/', $text, $phones);
        $allowedDigits = array_map(fn ($c) => preg_replace('/\D/', '', $c), $ctx['allowed_contacts']);
        foreach ($phones[0] as $p) {
            $digits = preg_replace('/\D/', '', $p);
            if (strlen($digits) >= 7 && ! in_array($digits, $allowedDigits, true)) {
                return "contains a phone number that was not provided ({$p}).";
            }
        }

        // Claims: keep only those that quote the body and cite a real evidence row.
        $claims = [];
        $norm = self::norm($body);
        foreach ((array) ($d['claims'] ?? []) as $c) {
            if (! is_array($c)) {
                continue;
            }
            $id = (int) ($c['evidence_id'] ?? 0);
            $quote = trim((string) ($c['quote'] ?? ''));
            if ($id && in_array($id, $ctx['evidence_ids'], true) && mb_strlen($quote) >= 8 && str_contains($norm, self::norm($quote))) {
                $claims[] = ['quote' => $quote, 'evidence_id' => $id];
            }
        }

        $warnings = [];
        if ($approach === 'problem_solving') {
            if ($ctx['has_evidence'] && ! $claims) {
                return 'must cite at least one piece of evidence about the company (claims[].evidence_id and a quote from the body).';
            }
            if (! $ctx['has_evidence']) {
                $warnings[] = 'No evidence is saved for this opportunity, so this draft cannot make a verified observation. Run research or add evidence first.';
            }
        }

        $projectId = null;
        if ($approach === 'experience_led') {
            if ($ctx['projects']) {
                $projectId = (int) ($d['project_id'] ?? 0);
                if (! isset($ctx['projects'][$projectId])) {
                    return 'must reference one of the provided projects (project_id).';
                }
                if (mb_stripos($body, $ctx['projects'][$projectId]) === false) {
                    return 'must name the project it cites ('.$ctx['projects'][$projectId].').';
                }
            } else {
                $warnings[] = 'No portfolio project is linked, so this draft has no project to point to.';
            }
        }

        return [
            'approach' => $approach,
            'subject' => $subject,
            'alt_subjects' => $alts,
            'body' => $body,
            'claims' => $claims,
            'evidence_ids' => array_values(array_unique(array_column($claims, 'evidence_id'))),
            'project_id' => $projectId,
            'warnings' => $warnings,
        ];
    }

    public static function wordCount(string $s): int
    {
        return count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY));
    }

    private static function norm(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($s)));
    }

    /** Jaccard similarity of the longer words; 1.0 = same vocabulary. */
    public static function similarity(string $a, string $b): float
    {
        $set = function (string $s) {
            preg_match_all('/\p{L}{4,}/u', mb_strtolower($s), $m);

            return array_unique($m[0]);
        };
        $x = $set($a);
        $y = $set($b);
        $union = count(array_unique(array_merge($x, $y)));

        return $union ? count(array_intersect($x, $y)) / $union : 0.0;
    }
}
