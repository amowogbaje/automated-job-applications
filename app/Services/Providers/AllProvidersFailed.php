<?php

namespace App\Services\Providers;

use RuntimeException;

class AllProvidersFailed extends RuntimeException
{
    public function __construct(public string $kind, public array $attempts)
    {
        $label = $kind === 'ai' ? 'AI' : 'search';

        if ($attempts === []) {
            $message = "No {$label} provider is enabled. Enable one under Settings → Providers.";
        } else {
            $parts = array_map(fn ($a) => $a['provider'].' ('.$a['outcome'].($a['detail'] ? ': '.$a['detail'] : '').')', $attempts);
            $message = "No {$label} provider could complete the request: ".implode('; ', $parts);
        }

        parent::__construct($message);
    }
}
