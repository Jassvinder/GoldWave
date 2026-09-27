<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * T-131 (19-09-2026) — which "door" a session logged in through. A Store
 * Owner who is also a Member has one `users` row (`role=admin`) but two
 * logins: the Member login (Customer ID / OTP) stamps `member`, the Store ID
 * login stamps `store`. Only used to choose between the Member Portal and the
 * Store Portal for that dual-identity user — `users.role` remains the source
 * of truth for every other authorization decision.
 */
class Portal
{
    public const KEY = 'goldwave_portal';

    public const MEMBER = 'member';

    public const STORE = 'store';

    public static function current(Request $request): ?string
    {
        $value = $request->session()->get(self::KEY);

        return is_string($value) ? $value : null;
    }

    public static function stamp(string $portal): void
    {
        session([self::KEY => $portal]);
    }
}
