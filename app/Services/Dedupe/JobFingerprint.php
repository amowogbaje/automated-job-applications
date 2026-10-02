<?php

namespace App\Services\Dedupe;

/**
 * Turns a listing into stable identity keys so the SAME job found on several boards, tweeted, and
 * cross-posted to Reddit/Lemmy/Mastodon is stored ONCE (the others are recorded as "also posted on").
 * Pure PHP so it's unit-tested without Laravel.
 *
 * Three independent signals, checked strongest first:
 *   apply_key    - the same ATS job id, the same canonical apply URL, or the same apply email + title
 *   fingerprint  - normalised company + normalised title ("Acme GmbH" + "Senior Laravel Dev (m/w/d)")
 *   simhash      - near-identical post TEXT (catches a tweet re-posted on Mastodon/Bluesky/Reddit)
 */
class JobFingerprint
{
    /** Legal-form words that differ between sources but mean the same company. */
    private const COMPANY_NOISE = ['inc', 'incorporated', 'llc', 'ltd', 'limited', 'gmbh', 'ag', 'sa', 'sarl', 'bv', 'nv', 'oy', 'ab', 'plc', 'corp', 'corporation', 'co', 'company', 'group', 'holdings', 'technologies', 'technology', 'tech', 'labs', 'the'];

    /** Words in titles that vary by board without changing the job. */
    private const TITLE_NOISE = ['remote', 'hybrid', 'onsite', 'on-site', 'worldwide', 'anywhere', 'urgent', 'hiring', 'wanted', 'job', 'opening', 'position', 'role', 'vacancy', 'fulltime', 'full-time', 'parttime', 'part-time', 'contract', 'm/w/d', 'm/f/d', 'w/m/d', 'f/m/d', 'mwd', 'gn', 'all', 'genders'];

    private const TRACKING_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'ref', 'ref_src', 'referrer', 'source', 'src', 'gh_src', 'gh_jid_src', 'lever-source', 'lever-origin', 'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'trk', 'trackingid', 's', 't', 'campaign', 'via', 'ssr', 'jvo'];

    public static function normalizeCompany(?string $company): string
    {
        $c = self::ascii((string) $company);
        $c = preg_replace('/[^a-z0-9 ]+/', ' ', $c);
        $words = array_values(array_filter(explode(' ', $c), fn ($w) => $w !== '' && ! in_array($w, self::COMPANY_NOISE, true)));

        return implode('', $words); // "acme labs gmbh" -> "acme"
    }

    public static function normalizeTitle(?string $title): string
    {
        $t = self::ascii((string) $title);
        // gender markers like (m/w/d) and bracketed noise first
        $t = preg_replace('/[\(\[]\s*(?:m\s*[\/|]\s*[wf]\s*[\/|]\s*d|w\s*[\/|]\s*m\s*[\/|]\s*d|f\s*[\/|]\s*m\s*[\/|]\s*d|all genders|gn)\s*[\)\]]/i', ' ', $t);
        $t = preg_replace('/[\(\[][^\)\]]*(?:remote|hybrid|worldwide|anywhere|usa|us only|europe|emea)[^\)\]]*[\)\]]/i', ' ', $t);
        $t = preg_replace('/[^a-z0-9+# ]+/', ' ', $t);
        $words = array_values(array_filter(explode(' ', $t), fn ($w) => $w !== '' && ! in_array($w, self::TITLE_NOISE, true)));
        sort($words); // order-insensitive: "Laravel Developer Senior" == "Senior Laravel Developer"

        return implode(' ', $words);
    }

    /** sha1(company|title), or null when there isn't enough to identify a job (e.g. no company). */
    public static function fingerprint(?string $company, ?string $title): ?string
    {
        $c = self::normalizeCompany($company);
        $t = self::normalizeTitle($title);
        if ($c === '' || $t === '' || strlen($t) < 4) {
            return null;
        }

        return sha1($c . '|' . $t);
    }

    /** Strip tracking junk so the same link from two sources compares equal. */
    public static function canonicalUrl(?string $url): ?string
    {
        if (! $url || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $parts = parse_url(trim($url));
        if (! $parts || empty($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^(www|m|mobile)\./i', '', strtolower($parts['host']));
        $path = rtrim($parts['path'] ?? '', '/');

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $q);
            foreach ($q as $k => $v) {
                if (! in_array(strtolower((string) $k), self::TRACKING_PARAMS, true) && ! is_array($v)) {
                    $query[$k] = $v;
                }
            }
            ksort($query);
        }

        return $host . $path . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * The strongest "this is literally the same posting" key available:
     *   ats:{vendor}:{board}:{id}  >  url:{canonical apply url}  >  null
     */
    public static function applyKey(?string $ats, ?string $board, ?string $jobId, ?string $applyUrl): ?string
    {
        if ($ats && $board && $jobId) {
            return strtolower("ats:{$ats}:{$board}:{$jobId}");
        }
        $canon = self::canonicalUrl($applyUrl);
        // Links that only point at a social post / the board homepage don't identify a job.
        if ($canon && ! preg_match('#^(x\.com|twitter\.com|bsky\.app|news\.ycombinator\.com|reddit\.com|old\.reddit\.com)#', $canon) && strlen($canon) > 12 && str_contains($canon, '/')) {
            return 'url:' . $canon;
        }

        return null;
    }

    /** 64-bit SimHash of a post's wording, as 16 hex chars; null for text too short to compare. */
    public static function simhash(?string $text): ?string
    {
        $t = self::ascii((string) $text);
        $t = preg_replace('#https?://\S+#', ' ', $t);          // links differ between cross-posts (t.co etc.)
        $t = preg_replace('/[@#][a-z0-9_]+/', ' ', $t);        // handles and hashtags too
        $t = preg_replace('/[^a-z0-9 ]+/', ' ', $t);
        $tokens = array_values(array_filter(explode(' ', $t), fn ($w) => strlen($w) > 2));
        if (count($tokens) < 8) {
            return null;
        }

        // 3-word shingles make it order-aware without being brittle.
        $features = [];
        for ($i = 0; $i + 2 < count($tokens); $i++) {
            $features[] = $tokens[$i] . ' ' . $tokens[$i + 1] . ' ' . $tokens[$i + 2];
        }

        $hi = array_fill(0, 32, 0);
        $lo = array_fill(0, 32, 0);
        foreach ($features as $f) {
            $h = md5($f);
            $a = hexdec(substr($h, 0, 8));
            $b = hexdec(substr($h, 8, 8));
            for ($bit = 0; $bit < 32; $bit++) {
                $hi[$bit] += (($a >> $bit) & 1) ? 1 : -1;
                $lo[$bit] += (($b >> $bit) & 1) ? 1 : -1;
            }
        }

        $A = $B = 0;
        for ($bit = 0; $bit < 32; $bit++) {
            if ($hi[$bit] > 0) {
                $A |= (1 << $bit);
            }
            if ($lo[$bit] > 0) {
                $B |= (1 << $bit);
            }
        }

        return sprintf('%08x%08x', $A & 0xFFFFFFFF, $B & 0xFFFFFFFF);
    }

    /** Hamming distance between two simhashes (0 = identical, <=3 = near-duplicate text). */
    public static function distance(?string $a, ?string $b): int
    {
        if (! $a || ! $b || strlen($a) !== 16 || strlen($b) !== 16) {
            return 64;
        }
        $d = 0;
        foreach ([[0, 8], [8, 8]] as [$off, $len]) {
            $x = hexdec(substr($a, $off, $len)) ^ hexdec(substr($b, $off, $len));
            while ($x) {
                $d += $x & 1;
                $x >>= 1;
            }
        }

        return $d;
    }

    public static function ascii(string $s): string
    {
        $s = mb_strtolower($s);
        $map = ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'ñ' => 'n', 'ó' => 'o', 'á' => 'a', 'í' => 'i', 'ú' => 'u', 'ã' => 'a', 'õ' => 'o', 'ô' => 'o', 'î' => 'i', 'ï' => 'i', 'û' => 'u', 'ù' => 'u', '’' => "'", '–' => '-', '—' => '-'];

        return strtr($s, $map);
    }
}
