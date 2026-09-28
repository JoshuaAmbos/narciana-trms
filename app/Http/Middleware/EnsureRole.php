<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the request only if the logged-in user has one of the given roles.
 * Usage in routes: ->middleware('role:Owner')  or  ->middleware('role:Owner,Manager')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()?->hasRole(...$roles)) {
            abort(403);
        }

        return $next($request);
    }
}
