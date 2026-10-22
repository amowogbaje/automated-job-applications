<?php

namespace App\Services\Agent\Discovery;

use App\Services\Agent\Research\EvidenceVerifier;
use App\Services\Agent\Research\UrlGuard;
use App\Services\Agent\Research\UrlHelper;

/**
 * Integrity checks on what the model proposes. A candidate only survives when it points at a source
 * we actually gave it and quotes that source word for word. Pure PHP so it is unit-testable.
 */
class CandidateFilter
{
    public const CONFIDENCE = ['low', 'moderate', 'high'];
    public const JUNK = ['wikipedia.org', 'youtube.com', 'facebook.com', 'instagram.com', 'pinterest.com', 'quora.com', 'tiktok.com', 'medium.com', 'glassdoor.com', 'indeed.com'];
    public const TYPES = ['job_posting', 'rfp', 'growth_signal', 'inferred_need', 'prospect'];
    public const SOCIAL = ['facebook.com', 'instagram.com', 'tiktok.com', 'linkedin.com', 'x.com', 'twitter.com', 'linktr.ee'];
    public const MODES = ['remote', 'hybrid', 'onsite'];

    public static function isSocial(string $url): bool
    {
        $host = UrlHelper::host($url);
        foreach ((array) (function_exists('config') ? config('discovery.social_domains', self::SOCIAL) : self::SOCIAL) as $d) {
            if ($host && ($host === $d || str_ends_with($host, '.'.$d))) {
                return true;
            }
        }

        return false;
    }

    /** Social pages are normal sources when prospecting (a barber's Instagram is the business), junk otherwise. */
    public static function isJunk(string $url, bool $allowSocial = false): bool
    {
        $host = UrlHelper::host($url);
        if (! $host) {
            return true;
        }
        if ($allowSocial && self::isSocial($url)) {
            return false;
        }
        foreach ((array) (function_exists('config') ? config('discovery.junk_domains', self::JUNK) : self::JUNK) as $junk) {
            if ($host === $junk || str_ends_with($host, '.'.$junk)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array  $item  one entry of the model's "candidates"
     * @param  array<string, array{url: string, title: string, text: string}>  $sources  keyed S1, S2...
     * @return array{ok: true, data: array}|array{ok: false, reason: string}
     */
    public static function check(array $item, array $sources, string $defaultType, array $allowedModes = [], string $strategy = ''): array
    {
        $id = (string) ($item['source'] ?? '');
        if (! isset($sources[$id])) {
            return ['ok' => false, 'reason' => 'cites a source we did not provide'];
        }

        $name = trim(preg_replace('/\s+/', ' ', (string) ($item['company_name'] ?? '')));
        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'reason' => 'no usable company name'];
        }

        $finding = trim((string) ($item['finding'] ?? ''));
        $excerpt = trim((string) ($item['excerpt'] ?? ''));
        if ($finding === '' || $excerpt === '') {
            return ['ok' => false, 'reason' => 'no finding or no quote'];
        }
        if (! EvidenceVerifier::containsExcerpt($sources[$id]['title'].' '.$sources[$id]['text'], $excerpt)) {
            return ['ok' => false, 'reason' => 'quote is not in the source'];
        }

        $mode = strtolower(trim((string) ($item['work_mode'] ?? '')));
        $mode = in_array($mode, self::MODES, true) ? $mode : null;
        $allowed = array_values(array_intersect(self::MODES, array_map('strtolower', $allowedModes)));
        if ($mode && $allowed && count($allowed) < 3 && ! in_array($mode, $allowed, true)) {
            return ['ok' => false, 'reason' => "work arrangement ({$mode}) does not fit your service"];
        }

        $prospecting = $strategy === 'prospecting';
        if ($prospecting && ! empty($item['is_technology_company'])) {
            return ['ok' => false, 'reason' => 'is a technology company, not a prospect'];
        }

        $website = trim((string) ($item['website_url'] ?? ''));
        if ($website !== '' && (! preg_match('#^https?://#i', $website) || ! UrlGuard::isPublicHttpUrl($website) || self::isJunk($website))) {
            $website = '';
        }

        $type = $prospecting ? 'prospect' : (in_array($item['type'] ?? '', self::TYPES, true) ? $item['type'] : $defaultType);
        $confidence = in_array($item['confidence'] ?? '', self::CONFIDENCE, true) ? $item['confidence'] : 'low';

        $presence = $website !== '' ? 'website' : (self::isSocial($sources[$id]['url']) ? 'social_only' : 'unknown');
        $contacts = $prospecting ? self::contacts((array) ($item['contacts'] ?? []), $sources[$id], $website) : [];
        if ($prospecting && self::isSocial($sources[$id]['url'])) {
            // The page itself is a public route to the business: you message it by hand.
            $contacts[] = ['type' => 'professional_profile', 'value' => mb_substr($sources[$id]['url'], 0, 300), 'label' => 'Social page (message them yourself)'];
        }

        return ['ok' => true, 'data' => [
            'source_id' => $id,
            'company_name' => $name,
            'website_url' => $website !== '' ? $website : null,
            'country' => self::country($item['country'] ?? null),
            'industry' => self::short($item['industry'] ?? null, 80),
            'title' => self::short($item['title'] ?? null, 160) ?: 'Opportunity at '.$name,
            'type' => $type,
            'finding' => mb_substr($finding, 0, 600),
            'establishes' => self::short($item['establishes'] ?? null, 400),
            'inference' => self::short($item['inference'] ?? null, 400),
            'excerpt' => mb_substr($excerpt, 0, 300),
            'confidence' => $confidence,
            'work_mode' => $mode,
            'contacts' => $contacts,
            'web_presence' => $presence,
        ]];
    }

    /**
     * Contact routes are kept only when they are written in the source we supplied (an email or
     * phone number the model "remembered" is dropped). Contact-form links must be on the source's
     * or the company's own host.
     *
     * @return array<int, array{type:string, value:string, label:?string}>
     */
    public static function contacts(array $raw, array $source, string $website = ''): array
    {
        $text = (string) ($source['title'] ?? '').' '.(string) ($source['text'] ?? '');
        $lower = mb_strtolower($text);
        $digits = preg_replace('/\D+/', '', $text);
        $out = [];
        foreach (array_slice($raw, 0, 6) as $c) {
            if (! is_array($c)) {
                continue;
            }
            $type = (string) ($c['type'] ?? '');
            $value = trim((string) ($c['value'] ?? ''));
            $label = self::short($c['label'] ?? null, 80);
            if ($value === '' || mb_strlen($value) > 300) {
                continue;
            }
            if ($type === 'email') {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL) || ! str_contains($lower, mb_strtolower($value))
                    || preg_match('/^(?:no-?reply|donotreply)@/i', $value)) {
                    continue;
                }
                $value = mb_strtolower($value);
            } elseif ($type === 'phone' || $type === 'whatsapp') {
                $d = preg_replace('/\D+/', '', $value);
                if (strlen($d) < 7 || strlen($d) > 15 || ! str_contains($digits, $d)) {
                    continue;
                }
            } elseif ($type === 'contact_form') {
                $host = UrlHelper::host($value);
                $allowedHosts = array_filter([UrlHelper::host((string) ($source['url'] ?? '')), $website !== '' ? UrlHelper::host($website) : null]);
                if (! preg_match('#^https?://#i', $value) || ! $host || ! in_array($host, $allowedHosts, true) || ! UrlGuard::isPublicHttpUrl($value)) {
                    continue;
                }
            } else {
                continue;
            }
            $out[$type.'|'.$value] = ['type' => $type, 'value' => $value, 'label' => $label];
        }

        return array_values($out);
    }

    private static function short(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function country(mixed $v): ?string
    {
        $v = strtoupper(trim((string) $v));

        return preg_match('/^[A-Z]{2}$/', $v) ? $v : null;
    }
}
