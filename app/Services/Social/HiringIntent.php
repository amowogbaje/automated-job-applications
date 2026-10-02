<?php

namespace App\Services\Social;

/**
 * Cheap pre-filter (no AI, no cost): does this post look like someone HIRING,
 * as opposed to a developer announcing they're available, or unrelated chatter?
 */
class HiringIntent
{
    private const HIRING = [
        '/\b(we(?:\'|’)?re|we are|is|are)\s+(?:currently\s+|now\s+|actively\s+)?hiring\b/i',
        '/\b(?:#|\[)\s*hiring\b/i',
        '/\bhiring\s*(?:[:\-–—!|\]]|for\b|a\b|an\b|now\b)/i',
        '/\b(?:we(?:\'|’)?re|we are|i(?:\'|’)?m|i am|currently|now)\s+(?:looking|searching|seeking)\s+for\s+(?:a|an|our|someone|talented|experienced|senior|junior|mid|full|backend|front|dev|engineer)/i',
        '/\b(?:job|role|position|opening|vacancy)\s+(?:alert|available|open)\b/i',
        '/\b(?:open|new)\s+(?:role|position|vacancy|opening)s?\b/i',
        '/\bjoin\s+(?:our|my|the)\s+team\b/i',
        '/\b(?:send|email|e-mail|mail|forward)\s+(?:us\s+|me\s+)?(?:your\s+)?(?:cv|resume|résumé|application|portfolio)\b/i',
        '/\bapply\s+(?:now|here|at|via|through|by|on|below|today)\b/i',
        '/\b(?:#|\[)\s*(?:fedihire|remotejob|remotejobs|techjobs|nowhiring|jobopening)\b/i',
        '/\b(?:full|part)[\s-]?time\b.*\b(?:developer|engineer|dev)\b/i',
    ];

    /** The author is the one looking for work: not an opening. */
    private const SEEKING_WORK = [
        '/\b(?:open|available)\s+(?:to|for)\s+(?:work|hire|new opportunities|freelance|projects)\b/i',
        '/#\s*(?:opentowork|forhire|hireme|available(?:for)?hire)\b/i',
        '/\bi(?:\'|’)?m\s+(?:a\s+)?(?:\w+\s+){0,3}(?:developer|engineer|designer)\b[^.]{0,60}\b(?:looking for|seeking)\s+(?:a\s+)?(?:job|role|work|opportunit|position|remote)/i',
        '/\blooking for (?:a |my next )?(?:new )?(?:job|role|opportunit|position)\b(?![^.]{0,30}\bfor (?:our|my|the) (?:team|company|startup))/i',
        '/\bhire me\b/i',
        '/\bmy (?:cv|resume|portfolio) is\b/i',
    ];

    public static function looksLikeJobPost(string $text): bool
    {
        $text = trim($text);
        if (mb_strlen($text) < 25) {
            return false;
        }

        foreach (self::SEEKING_WORK as $re) {
            if (preg_match($re, $text)) {
                return false;
            }
        }

        foreach (self::HIRING as $re) {
            if (preg_match($re, $text)) {
                return true;
            }
        }

        return false;
    }
}
