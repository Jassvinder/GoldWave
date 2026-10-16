<?php

namespace App\Http\Middleware;

use App\Models\Member;
use App\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — a member who is blocked while signed in is signed out on their next request and
 * sent to the Member login with the "blocked" message. Only the member side: a Store Owner's store login is not
 * affected by their member account's block.
 */
class EnsureMemberNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Staff never has a member account to block; the member row is loaded only for a member-side user.
        $onMemberSide = $user !== null && ($user->role === 'member' || ($user->role === 'store_admin' && Portal::current($request) === Portal::MEMBER));

        // Read straight from the database, never a relation cached on the user object.
        if ($onMemberSide && Member::where('user_id', $user->id)->whereNotNull('blocked_at')->exists()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('member.login')->withErrors(['customer_id' => Member::BLOCKED_LOGIN_MESSAGE]);
        }

        return $next($request);
    }
}
