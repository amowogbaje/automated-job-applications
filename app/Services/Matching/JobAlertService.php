<?php

namespace App\Services\Matching;

use App\Mail\JobAlertMail;
use App\Models\CareerProfile;
use App\Models\JobListingUserState;
use Illuminate\Support\Facades\Mail;

/**
 * Opt-in email alerts: only jobs that matched the skills the person chose, at or above their chosen
 * quality tier, each emailed at most once. Hourly people get mail as soon as something matches; daily
 * people get one email after DAILY_HOUR (app timezone).
 */
class JobAlertService
{
    public const DAILY_HOUR = 7;
    public const MAX_PER_EMAIL = 25;
    /** Anything older than this when it matched is already in the feed; an alert would be stale. */
    public const FRESH_HOURS = 48;

    /** @return int emails sent */
    public function sendDue(): int
    {
        $sent = 0;

        CareerProfile::query()
            ->where('digest_enabled', true)
            ->where('open_to_work', true)
            ->with('user')
            ->each(function (CareerProfile $profile) use (&$sent) {
                if ($profile->user && $this->isDue($profile) && $this->sendFor($profile)) {
                    $sent++;
                }
            });

        return $sent;
    }

    public function isDue(CareerProfile $profile): bool
    {
        if ($profile->alert_frequency !== 'daily') {
            return true; // hourly: the scheduler tick is the cadence
        }

        $now = now();

        return $now->hour >= self::DAILY_HOUR
            && (! $profile->last_alert_at || $profile->last_alert_at->copy()->timezone($now->timezone)->toDateString() < $now->toDateString());
    }

    /** Sends one email if there is anything new. Returns true when an email went out. */
    public function sendFor(CareerProfile $profile): bool
    {
        $to = $profile->notificationEmail();
        if (! $to || ! $profile->hasInterests()) {
            return false;
        }

        $base = JobListingUserState::query()
            ->where('user_id', $profile->user_id)
            ->whereNotNull('matched_at')
            ->whereNull('notified_at')
            ->where('is_dismissed', false)
            ->where('is_applied', false)
            ->whereIn('tier', $profile->alertTiers())
            ->whereHas('job', fn ($q) => $q->canonical()->where('created_at', '>=', now()->subHours(self::FRESH_HOURS)));

        // Fix the set up front so a match that lands mid-send is neither emailed half-way nor silently marked.
        $ids = (clone $base)->pluck('id');
        $total = $ids->count();

        if ($total === 0) {
            // Daily people: nothing today, check again tomorrow rather than every hour.
            if ($profile->alert_frequency === 'daily') {
                $profile->forceFill(['last_alert_at' => now()])->save();
            }

            return false;
        }

        $states = JobListingUserState::query()->whereIn('id', $ids->all())->with('job')
            ->orderByDesc('score')->orderByDesc('id')
            ->limit(self::MAX_PER_EMAIL)->get();

        Mail::to($to)->send(new JobAlertMail(
            states: $states,
            total: $total,
            skills: array_values((array) $profile->required_skills) ?: array_values((array) $profile->keywords),
        ));

        // Everything that was eligible is now "notified", including the overflow beyond the cap: the
        // feed still shows it, and it must not come back in tomorrow's email.
        JobListingUserState::query()->whereIn('id', $ids->all())->update(['notified_at' => now()]);
        $profile->forceFill(['last_alert_at' => now()])->save();

        return true;
    }
}
