<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * role:seeker / role:employer. Someone on the wrong side is sent to THEIR home instead of seeing a 403,
 * so a stale bookmark or a shared link never dead-ends.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        $ok = $role === 'employer' ? $user?->isEmployer() : $user?->isSeeker();
        if (! $ok) {
            return redirect()->route($user ? $user->homeRoute() : 'login');
        }

        return $next($request);
    }
}
