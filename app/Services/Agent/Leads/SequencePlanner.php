<?php

namespace App\Services\Agent\Leads;

use Carbon\CarbonInterface;

/**
 * Pure decision logic for the follow-up sequence (no database): given where a sequence is,
 * what should happen now? The runner carries the decision out.
 */
class SequencePlanner
{
    public const SEND_EMAIL = 'send_email';       // automatic email for step+1
    public const NOTIFY_EMAIL = 'notify_email';   // email step due but auto-send is off / blocked: tell the owner
    public const NOTIFY_PHONE = 'notify_phone';   // ask owner to message the phone
    public const SWITCH_TO_PHONE = 'switch_to_phone';
    public const FINISH = 'finish';               // no response: close out
    public const WAIT = 'wait';

    /**
     * @param array{mode:string,step:int,total:int,reminders:int,next_action_at:?CarbonInterface,has_phone:bool,has_email:bool,auto_on:bool,in_window:bool,cap_left:int} $s
     */
    public static function decide(array $s, CarbonInterface $now): string
    {
        if (! $s['next_action_at'] || $s['next_action_at']->gt($now)) {
            return self::WAIT;
        }

        if ($s['mode'] === 'email') {
            if ($s['step'] < $s['total']) {
                if (! $s['has_email']) {
                    return $s['has_phone'] ? self::SWITCH_TO_PHONE : self::FINISH;
                }

                return $s['auto_on'] && $s['in_window'] && $s['cap_left'] > 0 ? self::SEND_EMAIL : ($s['auto_on'] ? self::WAIT : self::NOTIFY_EMAIL);
            }

            // all emails sent and the grace period has passed
            return $s['has_phone'] ? self::SWITCH_TO_PHONE : self::FINISH;
        }

        // manual (phone / WhatsApp)
        if ($s['step'] < $s['total']) {
            return $s['reminders'] < (int) config('leads.manual_reminders', 2) + 1 ? self::NOTIFY_PHONE : self::FINISH;
        }

        return self::FINISH;
    }

    /** Days to wait before the next step, after completing step number $stepDone in email mode. */
    public static function emailDelayAfter(int $stepDone, array $delays, int $grace): int
    {
        // $delays[i] is the wait BEFORE step i+1; after the last step we wait the grace period.
        return $delays[$stepDone] ?? $grace;
    }
}
