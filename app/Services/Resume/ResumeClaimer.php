<?php

namespace App\Services\Resume;

use App\Models\Resume;
use App\Models\User;

class ResumeClaimer
{
    /**
     * @return array{resume: ?Resume, error: ?string}
     */
    public function claim(User $user, ?int $resumeId = null): array
    {
        $resume = $resumeId
            ? Resume::find($resumeId)
            : Resume::whereNull('user_id')->latest('id')->first();

        if (! $resume) {
            return ['resume' => null, 'error' => $resumeId
                ? "Resume #{$resumeId} not found."
                : 'No unowned resume to claim.'];
        }

        // A resume, once owned, is locked — never reassigned, no override.
        if ($resume->user_id) {
            return ['resume' => null, 'error' => $resume->user_id === $user->id
                ? "This resume is already yours."
                : "That resume already belongs to another account and can't be reassigned."];
        }

        // The real ownership check: the resume's own email has to match the
        // account trying to claim it, so nobody can grab someone else's
        // seeded resume just by registering first.
        if ($resume->email && strcasecmp($resume->email, $user->email) !== 0) {
            return ['resume' => null, 'error' => "That resume belongs to {$resume->email}, not {$user->email} — you can only claim a resume whose email matches your account."];
        }

        $resume->update(['user_id' => $user->id]);
        $resume->activate();

        return ['resume' => $resume, 'error' => null];
    }

    /**
     * Is there an unowned resume this user is even eligible to claim?
     * Used to decide whether to show the "Claim your resume" button at all.
     */
    public function claimableFor(User $user): ?Resume
    {
        return Resume::whereNull('user_id')
            ->where(function ($q) use ($user) {
                $q->whereNull('email')->orWhereRaw('LOWER(email) = ?', [strtolower($user->email)]);
            })
            ->latest('id')
            ->first();
    }
}
