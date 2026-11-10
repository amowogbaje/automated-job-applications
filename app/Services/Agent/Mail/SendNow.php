<?php

namespace App\Services\Agent\Mail;

use App\Models\GoogleAccount;
use App\Models\Setting;
use App\Models\User;

/** Who may press "Send now", and which mailbox the email leaves from. */
class SendNow
{
    public static function smtpUserEmail(): string
    {
        return strtolower(trim((string) (Setting::get('leads.smtp_user_email') ?: config('leads.smtp_user_email'))));
    }

    public static function enabled(): bool
    {
        return (bool) Setting::get('leads.send_now_enabled', true);
    }

    /** Pure decision. @return array{mode:string, can:bool, reason:?string} mode: off | smtp | google | connect | unconfigured */
    public static function decide(bool $enabled, string $smtpUser, string $userEmail, bool $hasGoogle, bool $googleConfigured): array
    {
        if (! $enabled) {
            return ['mode' => 'off', 'can' => false, 'reason' => 'Send now is switched off in Leads → Settings.'];
        }
        if ($smtpUser !== '' && strtolower($userEmail) === strtolower($smtpUser)) {
            return ['mode' => 'smtp', 'can' => true, 'reason' => null];
        }
        if ($hasGoogle) {
            return ['mode' => 'google', 'can' => true, 'reason' => null];
        }
        if (! $googleConfigured) {
            return ['mode' => 'unconfigured', 'can' => false, 'reason' => 'Google sign-in is not set up yet. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env (see INTEGRATION.md).'];
        }

        return ['mode' => 'connect', 'can' => false, 'reason' => 'Connect your Google account to send from your own Gmail.'];
    }

    /** @return array{mode:string, can:bool, reason:?string, from:?string} */
    public static function statusFor(User $user): array
    {
        $g = GoogleAccount::where('user_id', $user->id)->first();
        $s = self::decide(self::enabled(), self::smtpUserEmail(), $user->email, (bool) $g, GoogleMail::configured());
        $s['from'] = match ($s['mode']) {
            'smtp' => config('mail.from.address'),
            'google' => $g?->email,
            default => null,
        };

        return $s;
    }
}
