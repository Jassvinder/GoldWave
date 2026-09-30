<?php

use App\Http\Controllers\Member\EmiController;
use Illuminate\Support\Facades\Route;

/**
 * DOMAIN_LOGIC.md §5/§10 (T-005) — a member's own EMI schedule and the
 * recurring installment Payment In flow. Member-only (Super Admin has no
 * standing reason to pay another member's installment).
 */
Route::middleware(['auth', 'member-portal'])->prefix('member')->name('member.')->group(function () {
    Route::get('emi', [EmiController::class, 'index'])->name('emi.index');
    Route::post('emi/{installment}/pay', [EmiController::class, 'pay'])->name('emi.pay');
    // T-184 — pay every remaining EMI at once.
    Route::post('emi/pay-all', [EmiController::class, 'payAll'])->name('emi.pay-all');
    // T-185a — the same for a store Repurchase on EMI.
    Route::post('emi/store/{booking}/pay-all', [EmiController::class, 'payAllStore'])->name('emi.store-pay-all');
});
