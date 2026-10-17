<?php

namespace App\Services\Agent\Providers;

use Illuminate\Http\Client\Response;
use RuntimeException;

/** A provider call failed in a way the failover layer understands. */
class ProviderException extends RuntimeException
{
    public const RATE_LIMITED = 'rate_limited';
    public const AUTH = 'auth';
    public const SERVER = 'server';
    public const NETWORK = 'network';
    public const REJECTED = 'rejected';

    public function __construct(
        public string $kind,
        string $message,
        public ?int $status = null,
        public ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $body = $response->json();
        $message = data_get($body, 'error.message')
            ?? data_get($body, '0.error.message')
            ?? data_get($body, 'message')
            ?? data_get($body, 'detail.error')
            ?? mb_substr(trim($response->body()), 0, 300);
        $message = is_string($message) ? $message : json_encode($message);
        $message = "HTTP {$status}: ".mb_substr($message ?: 'no details', 0, 300);

        $retry = $response->header('Retry-After');
        $retryAfter = is_numeric($retry) ? (int) $retry : null;

        // 402/432/433 are quota-exhausted codes used by some search APIs: back off for an hour.
        if (in_array($status, [402, 432, 433], true)) {
            return new self(self::RATE_LIMITED, $message, $status, $retryAfter ?? 3600);
        }

        return match (true) {
            $status === 429 => new self(self::RATE_LIMITED, $message, $status, $retryAfter),
            in_array($status, [401, 403], true) => new self(self::AUTH, $message, $status),
            $status >= 500 => new self(self::SERVER, $message, $status),
            default => new self(self::REJECTED, $message, $status),
        };
    }

    /** How long to stop using this provider after this failure. */
    public function cooldownSeconds(): int
    {
        return match ($this->kind) {
            self::RATE_LIMITED => min(max($this->retryAfter ?? 60, 5), 3600),
            self::AUTH => 900,
            self::SERVER, self::NETWORK => 30,
            default => 0,
        };
    }
}
