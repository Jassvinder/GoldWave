<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RequestPasswordResetOtp;
use App\Actions\Auth\SetNewPassword;
use App\Actions\Auth\VerifyPasswordResetOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §2.2 — OTP is the only gate for a password change; there is
 * no separate "forgot password" bypass. SetNewPassword enforces this
 * server-side (session flag set only by VerifyPasswordResetOtp), so this
 * controller's ordering is not itself the security boundary.
 */
class GoldWavePasswordResetController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/goldwave-password-reset');
    }

    public function requestOtp(RequestOtpRequest $request, RequestPasswordResetOtp $action): RedirectResponse
    {
        $action($request->string('identifier')->toString());

        return back()->with('status', 'A one-time code has been sent.');
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyPasswordResetOtp $action): RedirectResponse
    {
        $action($request->string('identifier')->toString(), $request->string('otp')->toString());

        return back()->with('status', 'Code verified — set your new password.');
    }

    public function setPassword(SetPasswordRequest $request, SetNewPassword $action): RedirectResponse
    {
        $action($request->string('password')->toString());
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Password updated.');
    }
}
