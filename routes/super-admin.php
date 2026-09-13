<?php

use App\Http\Controllers\SuperAdmin\CashPaymentApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('cash-payments', [CashPaymentApprovalController::class, 'index'])->name('cash-payments.index');
    Route::post('cash-payments/{payment}/approve', [CashPaymentApprovalController::class, 'approve'])->name('cash-payments.approve');
    Route::post('cash-payments/{payment}/reject', [CashPaymentApprovalController::class, 'reject'])->name('cash-payments.reject');
});
