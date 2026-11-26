<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * ARCHITECTURE.md "Password re-entry for sensitive Super Admin actions" — the save-side twin of `password.confirm`.
 *
 * `password.confirm` redirects to the password page, which is right for opening a page but loses a filled form: the
 * Super Admin confirms, lands back on the list and the plan/rule change was never saved. On a save this middleware
 * instead answers with a `confirm_password` field error, so the form stays open with its data and asks for the
 * password in place. When the form sends `confirm_password`, it is checked, the confirmation is renewed and the save
 * goes ahead; the password itself never reaches the controller.
 */
class EnsurePasswordRecentlyConfirmed
{
    public function handle(Request $request, Closure $next, int $timeoutSeconds = 300): Response
    {
        $password = $request->input('confirm_password');
        $request->offsetUnset('confirm_password');

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if (time() - $confirmedAt <= $timeoutSeconds) {
            return $next($request);
        }

        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages([
                'confirm_password' => 'For security, enter your password again to save. It has been more than '
                    .intdiv($timeoutSeconds, 60).' minutes since you last confirmed it.',
            ]);
        }

        if (! Hash::check($password, (string) $request->user()?->getAuthPassword())) {
            throw ValidationException::withMessages(['confirm_password' => 'The password is incorrect.']);
        }

        $request->session()->passwordConfirmed();

        return $next($request);
    }
}
