<?php

use App\Http\Controllers\Member\BoosterController;
use App\Http\Controllers\Member\ChangeRequestController;
use App\Http\Controllers\Member\DrawController;
use App\Http\Controllers\Member\LevelIncomeController;
use App\Http\Controllers\Member\MembershipController;
use App\Http\Controllers\Member\NotificationController;
use App\Http\Controllers\Member\PairRewardController;
use App\Http\Controllers\Member\PaymentHistoryController;
use App\Http\Controllers\Member\PayoutController;
use App\Http\Controllers\Member\PendingProfileController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\ReportsController;
use App\Http\Controllers\Member\WalletController;
use Illuminate\Support\Facades\Route;

/**
 * INSTRUCTIONS.md's Member Portal (T-015) — M02-M05, M07, M10-M18. M01
 * (Dashboard) is the shared `/dashboard` route (`DashboardController`); M06
 * (EMI) and M08/M09 (Directs/Tree) already have their own route files
 * (`member-emi.php`, `member-network.php`) from T-004/T-005.
 */
Route::middleware(['auth', 'role:member'])->prefix('member')->name('member.')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('pending-profile', [PendingProfileController::class, 'create'])->name('pending-profile.create');
    Route::post('pending-profile', [PendingProfileController::class, 'store'])->name('pending-profile.store');

    Route::get('change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::post('change-requests', [ChangeRequestController::class, 'store'])->name('change-requests.store');

    Route::get('membership', [MembershipController::class, 'show'])->name('membership.show');

    Route::get('payment-history', [PaymentHistoryController::class, 'index'])->name('payment-history.index');

    Route::get('level-income', [LevelIncomeController::class, 'index'])->name('level-income.index');

    Route::get('pair-reward', [PairRewardController::class, 'index'])->name('pair-reward.index');

    Route::get('booster', [BoosterController::class, 'index'])->name('booster.index');

    Route::get('draw', [DrawController::class, 'index'])->name('draw.index');

    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');

    Route::get('payout', [PayoutController::class, 'index'])->name('payout.index');
    Route::post('payout', [PayoutController::class, 'store'])->name('payout.store');

    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/export', [ReportsController::class, 'download'])->name('reports.export');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
