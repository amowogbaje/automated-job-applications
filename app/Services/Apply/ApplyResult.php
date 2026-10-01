<?php

namespace App\Services\Apply;

final class ApplyResult
{
    public const SUBMITTED = 'submitted';      // accepted by the employer's ATS
    public const PENDING = 'pending';          // handed to a provider; its webhook reports the outcome
    public const NEEDS_INPUT = 'needs_input';  // required question we can't answer truthfully
    public const FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?string $externalRef = null,
        public ?string $receiptUrl = null,
        public array $questions = [],
        public ?string $error = null,
    ) {}

    public static function submitted(?string $ref = null, ?string $receipt = null): self
    {
        return new self(self::SUBMITTED, $ref, $receipt);
    }

    public static function pending(string $ref): self
    {
        return new self(self::PENDING, $ref);
    }

    public static function needsInput(array $questions): self
    {
        return new self(self::NEEDS_INPUT, questions: $questions);
    }

    public static function failed(string $error): self
    {
        return new self(self::FAILED, error: mb_substr($error, 0, 1000));
    }
}
