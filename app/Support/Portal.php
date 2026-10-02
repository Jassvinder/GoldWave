<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * T-131 (19-09-2026) — which "door" a session logged in through. A Store
 * Owner who is also a Member has one `users` row (`role=admin`) but two
 * logins: the Member login (Customer ID / OTP) stamps `member`, the Store ID
 * login stamps `store`. Only used to choose between the Member Portal and the
 * Store Portal for that dual-identity user — `users.role` remains the source
 * of truth for every other authorization decision.
 *
 * T-198 (30-09-2026) — the door is also remembered in a long-lived cookie, because the session (and its stamp) is
 * gone by the time a member's session has expired or they have logged out. It only decides which login page to show.
 */
class Portal
{
    public const KEY = 'goldwave_portal';

    public const MEMBER = 'member';

    public const STORE = 'store';

    /** Super Admin / company Admin (Fortify email + password login). */
    public const STAFF = 'staff';

    public const COOKIE = 'goldwave_last_portal';

    public static function current(Request $request): ?string
    {
        $value = $request->session()->get(self::KEY);

        return is_string($value) ? $value : null;
    }

    public static function stamp(string $portal): void
    {
        session([self::KEY => $portal]);
        Cookie::queue(self::COOKIE, $portal, 60 * 24 * 365);
    }

    /** The door this browser last logged in through, even after the session ended. */
    public static function lastUsed(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) ? $value : null;
    }

    /** Where a logged-out visitor should sign in: a member page or a member's last login → the Member login. */
    public static function loginUrlFor(Request $request): string
    {
        if ($request->is('member', 'member/*') || self::lastUsed($request) === self::MEMBER) {
            return route('member.login');
        }

        return route('login');
    }
}
