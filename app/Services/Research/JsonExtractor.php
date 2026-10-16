<?php

namespace App\Services\Research;

class JsonExtractor
{
    /** Tolerant parse of model output: handles code fences, <think> blocks and chatter around the object. */
    public static function parse(string $text): ?array
    {
        $t = preg_replace('#<think>.*?</think>#is', '', $text) ?? $text;
        $t = trim($t);

        if (preg_match('/```(?:json)?\s*(.*?)```/is', $t, $m)) {
            $t = trim($m[1]);
        }

        $d = json_decode($t, true);
        if (is_array($d)) {
            return $d;
        }

        $start = strpos($t, '{');
        $end = strrpos($t, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $d = json_decode(substr($t, $start, $end - $start + 1), true);
            if (is_array($d)) {
                return $d;
            }
        }

        return null;
    }
}
