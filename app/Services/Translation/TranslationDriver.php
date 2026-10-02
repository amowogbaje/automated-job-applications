<?php

namespace App\Services\Translation;

interface TranslationDriver
{
    public function name(): string;

    /** Configured (key/URL present)? Unconfigured drivers are skipped silently. */
    public function available(): bool;

    /**
     * Characters this driver may still use in its current quota window
     * (PHP_INT_MAX when unlimited). Tracked locally, so it's a guard, not a promise.
     */
    public function remainingBudget(): int;

    /** Translate $text into $to. Returns null on any failure so the next driver can try. */
    public function translate(string $text, string $to = 'en'): ?string;
}
