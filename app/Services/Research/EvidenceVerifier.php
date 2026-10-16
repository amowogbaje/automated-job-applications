<?php

namespace App\Services\Research;

/**
 * Integrity checks on what the model returns: evidence must cite a real source we gave it,
 * and a quoted excerpt must actually appear in that source or it is dropped and confidence lowered.
 */
class EvidenceVerifier
{
    public const CONFIDENCE = ['low', 'moderate', 'high'];

    public static function normalize(string $s): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)) ?? '');
    }

    public static function containsExcerpt(string $source, string $excerpt): bool
    {
        $e = self::normalize($excerpt);

        return mb_strlen($e) >= 8 && str_contains(self::normalize($source), $e);
    }

    /**
     * @param  array  $items  model output
     * @param  array<string, array{url: string, title: string, text: string}>  $sources  keyed by id (S1, S2, ...)
     * @param  string[]  $kinds  allowed evidence kinds
     * @return array<int, array> rows ready for the evidence table (plus 'source_id')
     */
    public static function filter(array $items, array $sources, array $kinds, int $max = 6): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || count($out) >= $max) {
                continue;
            }
            $id = (string) ($item['source'] ?? '');
            $finding = trim((string) ($item['finding'] ?? ''));
            if ($finding === '' || ! isset($sources[$id])) {
                continue;
            }

            $confidence = in_array($item['confidence'] ?? '', self::CONFIDENCE, true) ? $item['confidence'] : 'moderate';
            $excerpt = trim((string) ($item['excerpt'] ?? ''));
            if ($excerpt !== '') {
                if (self::containsExcerpt($sources[$id]['text'], $excerpt)) {
                    $excerpt = mb_substr($excerpt, 0, 300);
                } else {
                    $excerpt = '';
                    $confidence = match ($confidence) {
                        'high' => 'moderate',
                        default => 'low',
                    };
                }
            }

            $out[] = [
                'source_id' => $id,
                'kind' => in_array($item['kind'] ?? '', $kinds, true) ? $item['kind'] : 'other',
                'source_url' => $sources[$id]['url'],
                'source_title' => mb_substr($sources[$id]['title'] ?? '', 0, 255) ?: null,
                'finding' => mb_substr($finding, 0, 1500),
                'establishes' => self::nullable($item['establishes'] ?? null, 1500),
                'inference' => self::nullable($item['inference'] ?? null, 1500),
                'confidence' => $confidence,
                'excerpt' => $excerpt !== '' ? $excerpt : null,
            ];
        }

        return $out;
    }

    private static function nullable(mixed $v, int $max): ?string
    {
        $v = is_string($v) ? trim($v) : '';

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
