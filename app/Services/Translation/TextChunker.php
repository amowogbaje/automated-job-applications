<?php

namespace App\Services\Translation;

/**
 * Splits text into chunks of at most $maxBytes UTF-8 bytes, preferring paragraph,
 * then sentence, then word boundaries (MyMemory allows 500 bytes per request;
 * other drivers use larger limits). Chunks joined with "" reproduce the text
 * (separators stay attached to the chunk before them), so re-assembly is lossless.
 */
class TextChunker
{
    /** @return string[] */
    public static function chunk(string $text, int $maxBytes = 450): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (strlen($text) <= $maxBytes) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        foreach (self::units($text, $maxBytes) as $unit) {
            if (strlen($current) + strlen($unit) <= $maxBytes) {
                $current .= $unit;
                continue;
            }
            if ($current !== '') {
                $chunks[] = $current;
            }
            $current = $unit;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @return string[] pieces that each fit in $maxBytes, keeping trailing whitespace attached */
    private static function units(string $text, int $maxBytes): array
    {
        // Sentence/paragraph-ish pieces, trailing whitespace kept.
        preg_match_all('/.+?(?:[.!?;:](?=\s)|\R+|$)\s*/su', $text, $m);
        $pieces = $m[0] ?: [$text];

        $out = [];
        foreach ($pieces as $piece) {
            if (strlen($piece) <= $maxBytes) {
                $out[] = $piece;
                continue;
            }
            // Oversized sentence: split on words, then hard-split as a last resort.
            $words = preg_split('/(?<=\s)/u', $piece, -1, PREG_SPLIT_NO_EMPTY) ?: [$piece];
            $buf = '';
            foreach ($words as $w) {
                while (strlen($w) > $maxBytes) {
                    if ($buf !== '') {
                        $out[] = $buf;
                        $buf = '';
                    }
                    $cut = self::safeCut($w, $maxBytes);
                    $out[] = substr($w, 0, $cut);
                    $w = substr($w, $cut);
                }
                if (strlen($buf) + strlen($w) > $maxBytes) {
                    $out[] = $buf;
                    $buf = '';
                }
                $buf .= $w;
            }
            if ($buf !== '') {
                $out[] = $buf;
            }
        }

        return $out;
    }

    /** Largest cut <= $max that doesn't split a multibyte character. */
    private static function safeCut(string $s, int $max): int
    {
        $cut = $max;
        while ($cut > 0 && (ord($s[$cut] ?? "\0") & 0xC0) === 0x80) {
            $cut--;
        }

        return max(1, $cut);
    }
}
