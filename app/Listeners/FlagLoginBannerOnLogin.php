<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * T-249 (INSTRUCTIONS.md M01) — every login (Customer ID + password, OTP, new password, passkey, remembered session)
 * marks the session so the member dashboard opens the welcome banner once. `DashboardController` takes the mark off
 * when it shows the banner, so a refresh or a later dashboard visit in the same session doesn't open it again.
 */
class FlagLoginBannerOnLogin
{
    public const SESSION_KEY = 'show_login_banner';

    public function handle(Login $event): void
    {
        $request = request();

        if ($request->hasSession()) {
            // A new key per login: the page remembers which key it has shown, so going Back to the dashboard
            // (restored from browser history) doesn't open the banner a second time.
            $request->session()->put(self::SESSION_KEY, Str::random(16));
        }
    }
}
