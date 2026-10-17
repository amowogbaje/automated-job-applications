<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keeps the personal Opportunity Agent away from other accounts on this multi-account site. */
class EnsureAgentOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canUseAgent()) {
            abort(404);
        }

        return $next($request);
    }
}
