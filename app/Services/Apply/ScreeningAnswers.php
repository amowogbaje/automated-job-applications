<?php

namespace App\Services\Apply;

/**
 * Answers recurring screening questions ONLY from facts the user wrote down
 * in /profile (career_profiles.screening_answers). Anything we can't match is
 * returned as unanswered so the application pauses as needs_input — we never
 * guess on questions with stakes (work authorization, salary, relocation...).
 */
class ScreeningAnswers
{
    public function __construct(private array $answers) {}

    /** @return string|null the user's answer, or null if none of their keys match the question label */
    public function forLabel(string $label): ?string
    {
        $label = strtolower($label);

        foreach ($this->answers as $key => $value) {
            $needle = strtolower(str_replace('_', ' ', (string) $key));
            if ($needle !== '' && $value !== null && $value !== '' && str_contains($label, $needle)) {
                return (string) $value;
            }
        }

        return null;
    }
}
