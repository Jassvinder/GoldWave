<?php

use App\Http\Controllers\Member\EmiController;
use Illuminate\Support\Facades\Route;

/**
 * DOMAIN_LOGIC.md §5/§10 (T-005) — a member's own EMI schedule and the
 * recurring installment Payment In flow. Member-only (Super Admin has no
 * standing reason to pay another member's installment).
 */
Route::middleware(['auth', 'role:member'])->prefix('member')->name('member.')->group(function () {
    Route::get('emi', [EmiController::class, 'index'])->name('emi.index');
    Route::post('emi/{installment}/pay', [EmiController::class, 'pay'])->name('emi.pay');
});
