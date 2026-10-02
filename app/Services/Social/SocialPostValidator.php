<?php

namespace App\Services\Social;

/**
 * The AI reads untrusted text from strangers, so its output is NEVER trusted as-is.
 * This turns the raw AI JSON into something safe to act on:
 *
 *  - an apply email is kept ONLY if that exact address appears in the post text
 *    (a hallucinated or "injected" address can never be emailed);
 *  - the same for an apply URL (must appear in the post text or its link entities);
 *  - the email subject is sanitised (no line breaks / header injection, length-capped);
 *  - if the post asks for anything we can't supply from a CV + cover letter
 *    (answers to questions, a video, salary figures...) it is flagged `needs_manual_input`
 *    and will never be auto-sent;
 *  - low-confidence extractions are never treated as an email application.
 *
 * Pure PHP: unit-tested without Laravel.
 */
class SocialPostValidator
{
    /** What we CAN send with an application. Anything else a post demands => manual. */
    private const SUPPLIABLE = ['cv', 'resume', 'résumé', 'curriculum vitae', 'cover letter', 'portfolio', 'portfolio link', 'github', 'github profile', 'linkedin', 'linkedin profile', 'links', 'website'];

    /**
     * @param  array<string,mixed>  $ai
     * @param  string[]  $entityUrls  URLs the platform itself attached to the post
     * @return array{is_job:bool, title:?string, company:?string, location:?string, remote:?bool, apply_email:?string, apply_url:?string, instructions:?string, subject:?string, needs_manual_input:bool, confidence:float, skills:string[]}
     */
    public static function validate(array $ai, string $text, array $entityUrls = [], float $minConfidence = 0.6): array
    {
        $confidence = max(0.0, min(1.0, (float) ($ai['confidence'] ?? 0)));
        $isJob = (bool) ($ai['is_job_post'] ?? false) && $confidence >= $minConfidence;

        $normalized = self::normalizeText($text);

        $email = self::cleanEmail($ai['apply_email'] ?? null);
        if ($email !== null && ! self::appearsIn($email, $normalized)) {
            $email = null; // not in the post: ignore, whatever the model claims
        }

        $url = self::cleanUrl($ai['apply_url'] ?? null);
        if ($url !== null && ! self::urlAppears($url, $text, $entityUrls)) {
            $url = null;
        }

        $materials = array_map(fn ($m) => mb_strtolower(trim((string) $m)), (array) ($ai['required_materials'] ?? []));
        $extraDemands = array_values(array_filter($materials, fn ($m) => $m !== '' && ! in_array($m, self::SUPPLIABLE, true)));
        $needsManual = (bool) ($ai['requires_extra_input'] ?? false) || $extraDemands !== [];

        // "DM me", "reply here", "comment below" mean an email isn't how you apply.
        $instructions = self::cleanLine($ai['application_instructions'] ?? null, 400);
        if ($email !== null && $instructions !== null && preg_match('/\b(dm|direct message|message me|reply|comment)\b/i', $instructions) && ! preg_match('/\bemail|e-mail|mail\b/i', $instructions)) {
            $email = null;
        }

        return [
            'is_job' => $isJob,
            'title' => self::cleanLine($ai['title'] ?? null, 140),
            'company' => self::cleanLine($ai['company'] ?? null, 120),
            'location' => self::cleanLine($ai['location'] ?? null, 120),
            'remote' => isset($ai['remote']) ? (bool) $ai['remote'] : null,
            'apply_email' => $isJob ? $email : null,
            'apply_url' => $isJob ? $url : null,
            'instructions' => $instructions,
            'subject' => self::cleanSubject($ai['required_subject'] ?? null),
            'needs_manual_input' => $needsManual,
            'confidence' => $confidence,
            'skills' => array_values(array_filter(array_map(fn ($s) => self::cleanLine($s, 60), (array) ($ai['skills'] ?? [])))),
        ];
    }

    /** Emails written as "name [at] domain [dot] com" become real addresses for comparison. */
    public static function normalizeText(string $text): string
    {
        $t = preg_replace('/\s*[\[\(\{]\s*at\s*[\]\)\}]\s*/i', '@', $text);
        $t = preg_replace('/\s*[\[\(\{]\s*dot\s*[\]\)\}]\s*/i', '.', $t);

        return mb_strtolower($t);
    }

    public static function extractEmails(string $text): array
    {
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+/i', self::normalizeText($text), $m);

        return array_values(array_unique(array_map(fn ($e) => rtrim($e, '.'), $m[0])));
    }

    public static function cleanEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = mb_strtolower(trim($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private static function appearsIn(string $email, string $normalizedText): bool
    {
        return in_array($email, self::extractEmails($normalizedText), true);
    }

    private static function cleanUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return preg_match('#^https?://[^\s]+$#i', $value) ? $value : null;
    }

    private static function urlAppears(string $url, string $text, array $entityUrls): bool
    {
        $bare = rtrim(preg_replace('#^https?://(www\.)?#i', '', $url), '/');
        foreach ($entityUrls as $e) {
            if (stripos($e, $bare) !== false || stripos($url, rtrim($e, '/')) !== false) {
                return true;
            }
        }

        return stripos($text, $bare) !== false;
    }

    private static function cleanLine(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** One line, no control characters (blocks email-header injection), capped. */
    public static function cleanSubject(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : mb_substr($value, 0, 120);
    }
}
