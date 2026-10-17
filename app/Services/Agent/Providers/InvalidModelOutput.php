<?php

namespace App\Services\Agent\Providers;

use RuntimeException;

/** The provider answered, but not with usable output. Try the next provider; no cooldown. */
class InvalidModelOutput extends RuntimeException
{
    public function __construct(string $message, public ?array $usage = null)
    {
        parent::__construct($message);
    }
}
