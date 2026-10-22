<?php

namespace App\Services\Agent\Search;

use App\Services\Agent\Providers\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class HttpSearchProvider implements SearchProvider
{
    public function __construct(protected string $key)
    {
    }

    protected function client(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(10)->timeout(config('search.timeout', 30));
    }

    /** Wraps a request so network and HTTP errors become ProviderExceptions. */
    protected function send(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new ProviderException(ProviderException::NETWORK, 'Connection problem: '.mb_substr($e->getMessage(), 0, 200));
        }
        if ($response->failed()) {
            throw ProviderException::fromResponse($response);
        }

        return $response;
    }

    /** Google-style recency code for "last N days": qdr:d, qdr:w, qdr:m (we never look further back than a month). */
    protected function qdr(?int $days): ?string
    {
        return match (true) {
            $days === null => null,
            $days <= 1 => 'qdr:d',
            $days <= 7 => 'qdr:w',
            default => 'qdr:m',
        };
    }

    protected function clean(?string $text, int $max = 400): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))), 0, $max);
    }
}
