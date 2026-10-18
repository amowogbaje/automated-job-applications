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
    public const TYPES = ['job_posting', 'rfp', 'growth_signal', 'inferred_need'];

    public static function isJunk(string $url): bool
    {
        $host = UrlHelper::host($url);
        if (! $host) {
            return true;
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
    public static function check(array $item, array $sources, string $defaultType): array
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

        $website = trim((string) ($item['website_url'] ?? ''));
        if ($website !== '' && (! preg_match('#^https?://#i', $website) || ! UrlGuard::isPublicHttpUrl($website) || self::isJunk($website))) {
            $website = '';
        }

        $type = in_array($item['type'] ?? '', self::TYPES, true) ? $item['type'] : $defaultType;
        $confidence = in_array($item['confidence'] ?? '', self::CONFIDENCE, true) ? $item['confidence'] : 'low';

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
        ]];
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
