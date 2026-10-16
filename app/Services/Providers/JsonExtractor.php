<?php

namespace App\Services\Providers;

/** Models wrap JSON in code fences or add chatter. Pull out the first JSON object safely. */
class JsonExtractor
{
    public static function decode(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new InvalidModelOutput('No JSON object found in the response.');
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            throw new InvalidModelOutput('Response was not valid JSON: '.json_last_error_msg());
        }

        return $data;
    }
}
