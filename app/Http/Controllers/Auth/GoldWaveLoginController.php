<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginWithCustomerIdPassword;
use App\Actions\Auth\RequestLoginOtp;
use App\Actions\Auth\VerifyLoginOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerIdLoginRequest;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §2.2 — GoldWave's Member login, distinct from the
 * Fortify-scaffolded generic `/login` (left untouched; a plausible fit for a
 * future Admin/Super Admin login, which the source spec never defines — see
 * PROGRESS.md). Two independent methods, both always available: OTP
 * (mobile-or-email) and Customer ID + password.
 */
class GoldWaveLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/goldwave-login');
    }

    public function requestOtp(RequestOtpRequest $request, RequestLoginOtp $action): RedirectResponse
    {
        $action($request->string('identifier')->toString());

        return back()->with('status', 'A one-time code has been sent.');
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyLoginOtp $action): RedirectResponse
    {
        $action($request->string('identifier')->toString(), $request->string('otp')->toString());
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function loginWithPassword(CustomerIdLoginRequest $request, LoginWithCustomerIdPassword $action): RedirectResponse
    {
        $action($request->string('customer_id')->toString(), $request->string('password')->toString());
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
