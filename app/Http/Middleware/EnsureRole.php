<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ARCHITECTURE.md Permissions table: `users.role` is the sole source of
 * truth for role checks — never inferred from route or the presence of a
 * `members` row.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
