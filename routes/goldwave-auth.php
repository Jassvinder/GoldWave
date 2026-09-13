<?php

use App\Http\Controllers\Auth\GoldWaveLoginController;
use App\Http\Controllers\Auth\GoldWavePasswordResetController;
use Illuminate\Support\Facades\Route;

/**
 * DOMAIN_LOGIC.md §2.2 — GoldWave's own Member login, at /member/* so it
 * never collides with the Fortify-scaffolded generic /login (left in place —
 * see routes/goldwave-auth.php's controller docblocks and PROGRESS.md).
 *
 * Throttled by IP (Laravel's default `throttle` middleware) — this login
 * does NOT go through Fortify, so Fortify's own login rate limiter
 * (FortifyServiceProvider) never applies here; SECURITY.md's "Customer-ID-
 * as-default-password" mitigation depends on these limits actually existing.
 * OTP verify is throttled higher than request/password (20/min) since
 * OtpService's own 5-attempts-per-code lockout is the primary guard there —
 * this is just a broader network-layer backstop, not the main control.
 */
Route::middleware('guest')->prefix('member')->name('member.')->group(function () {
    Route::get('login', [GoldWaveLoginController::class, 'show'])->name('login');
    Route::post('login/otp/request', [GoldWaveLoginController::class, 'requestOtp'])->middleware('throttle:6,1')->name('login.otp.request');
    Route::post('login/otp/verify', [GoldWaveLoginController::class, 'verifyOtp'])->middleware('throttle:20,1')->name('login.otp.verify');
    Route::post('login/password', [GoldWaveLoginController::class, 'loginWithPassword'])->middleware('throttle:6,1')->name('login.password');

    Route::get('password/reset', [GoldWavePasswordResetController::class, 'show'])->name('password.reset');
    Route::post('password/otp/request', [GoldWavePasswordResetController::class, 'requestOtp'])->middleware('throttle:6,1')->name('password.otp.request');
    Route::post('password/otp/verify', [GoldWavePasswordResetController::class, 'verifyOtp'])->middleware('throttle:20,1')->name('password.otp.verify');
    Route::post('password/set', [GoldWavePasswordResetController::class, 'setPassword'])->middleware('throttle:6,1')->name('password.set');
});
