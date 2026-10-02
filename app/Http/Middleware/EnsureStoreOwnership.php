<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DOMAIN_LOGIC.md §17.1/§17.2 — an Admin (Store Owner) is restricted to
 * their own assigned store, never any other store or company-wide data.
 * Resolves that one store from `stores.owner_user_id` and attaches it to the
 * request (`$request->attributes->get('store')`) so every Admin controller
 * reads from the same resolved instance rather than re-querying — this is
 * ARCHITECTURE.md's already-anticipated middleware, not yet built until
 * T-016 needed it.
 */
class EnsureStoreOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        // T-131: a Store Owner who logged in through the Member login is in the Member Portal
        // for this session — the Store Portal is only entered through the Store ID login.
        abort_if(Portal::current($request) === Portal::MEMBER, 403, 'Log in with your Store ID to use the Store Portal.');

        $user = $request->user();
        $store = $user ? Store::where('owner_user_id', $user->id)->first() : null;

        abort_if($store === null, 403, 'No store is assigned to this account.');

        $request->attributes->set('store', $store);

        return $next($request);
    }
}
