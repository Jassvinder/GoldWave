<?php

namespace App\Http\Middleware;

use App\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every Member Portal route (replaces `role:member`, T-131). A
 * `role=member` user always passes; a `role=store_admin` user (a Store Owner) passes
 * only if they also have a `members` row AND logged in through the Member
 * login this session — the Store ID login never opens the Member Portal.
 * Extra roles (e.g. `super_admin` for the Directs/Tree routes) are passed as
 * middleware parameters, exactly like `EnsureRole`.
 */
class EnsureMemberPortal
{
    public function handle(Request $request, Closure $next, string ...$extraRoles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->role === 'member' || in_array($user->role, $extraRoles, true)) {
            return $next($request);
        }

        if ($user->isStoreAdmin() && $user->member !== null && Portal::current($request) === Portal::MEMBER) {
            return $next($request);
        }

        abort(403);
    }
}
