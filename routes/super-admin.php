<?php

use App\Http\Controllers\SuperAdmin\AdminUserController;
use App\Http\Controllers\SuperAdmin\CashPaymentApprovalController;
use App\Http\Controllers\SuperAdmin\CompensationManagementController;
use App\Http\Controllers\SuperAdmin\DrawManagementController;
use App\Http\Controllers\SuperAdmin\DrawSettingsController;
use App\Http\Controllers\SuperAdmin\DummyEntryAssignmentController;
use App\Http\Controllers\SuperAdmin\DummyEntrySettingsController;
use App\Http\Controllers\SuperAdmin\MemberManagementController;
use App\Http\Controllers\SuperAdmin\MetalRateController;
use App\Http\Controllers\SuperAdmin\PayoutRequestController;
use App\Http\Controllers\SuperAdmin\PayoutTdsSettingsController;
use App\Http\Controllers\SuperAdmin\ProfileChangeRequestController;
use App\Http\Controllers\SuperAdmin\ReportsController;
use App\Http\Controllers\SuperAdmin\RuleVersionController;
use App\Http\Controllers\SuperAdmin\StoreManagementController;
use App\Http\Controllers\SuperAdmin\StoreWalletManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('cash-payments', [CashPaymentApprovalController::class, 'index'])->name('cash-payments.index');
    Route::post('cash-payments/{payment}/approve', [CashPaymentApprovalController::class, 'approve'])->name('cash-payments.approve');
    Route::post('cash-payments/{payment}/reject', [CashPaymentApprovalController::class, 'reject'])->name('cash-payments.reject');

    // S02 — Admin Users & Permissions.
    Route::get('admin-users', [AdminUserController::class, 'index'])->name('admin-users.index');
    Route::post('admin-users/find-member', [AdminUserController::class, 'findMember'])->name('admin-users.find-member');
    Route::post('admin-users', [AdminUserController::class, 'store'])->name('admin-users.store');
    Route::patch('admin-users/{admin_user}', [AdminUserController::class, 'update'])->name('admin-users.update');

    // S03 — Compensation Rule Versions (also Admin Compensation Management's config page, see below).
    Route::get('rule-versions', [RuleVersionController::class, 'index'])->name('rule-versions.index');
    Route::post('rule-versions', [RuleVersionController::class, 'store'])->name('rule-versions.store');

    // S04 — Daily Dummy Entry Settings.
    Route::get('dummy-entry-settings', [DummyEntrySettingsController::class, 'index'])->name('dummy-entry-settings.index');
    Route::post('dummy-entry-settings', [DummyEntrySettingsController::class, 'update'])->name('dummy-entry-settings.update');
    Route::post('dummy-entry-settings/generate', [DummyEntrySettingsController::class, 'generateNow'])->name('dummy-entry-settings.generate');

    // S05 — Dummy Entry Assignment.
    Route::get('dummy-entry-assignment', [DummyEntryAssignmentController::class, 'index'])->name('dummy-entry-assignment.index');
    Route::post('dummy-entry-assignment', [DummyEntryAssignmentController::class, 'store'])->name('dummy-entry-assignment.store');

    // S06 — Draw Master Settings + Admin Draw Management.
    Route::get('draw-settings', [DrawSettingsController::class, 'index'])->name('draw-settings.index');
    Route::post('draw-settings', [DrawSettingsController::class, 'update'])->name('draw-settings.update');
    Route::get('draw-management', [DrawManagementController::class, 'index'])->name('draw-management.index');
    Route::post('draw-management/{execution}/reconcile', [DrawManagementController::class, 'reconcile'])->name('draw-management.reconcile');

    // S07 — Gold & Silver Rate Settings.
    Route::get('metal-rates', [MetalRateController::class, 'index'])->name('metal-rates.index');
    Route::post('metal-rates', [MetalRateController::class, 'store'])->name('metal-rates.store');

    // S08 — Payout & TDS Settings.
    Route::get('payout-tds-settings', [PayoutTdsSettingsController::class, 'index'])->name('payout-tds-settings.index');
    Route::post('payout-tds-settings', [PayoutTdsSettingsController::class, 'update'])->name('payout-tds-settings.update');

    // S09 — Store Management.
    Route::get('store-management', [StoreManagementController::class, 'index'])->name('store-management.index');
    Route::post('store-management', [StoreManagementController::class, 'store'])->name('store-management.store');
    Route::get('store-management/{store}', [StoreManagementController::class, 'show'])->name('store-management.show');
    Route::post('store-management/{store}/reassign-owner', [StoreManagementController::class, 'reassignOwner'])->name('store-management.reassign-owner');
    Route::post('store-management/{store}/status', [StoreManagementController::class, 'updateStatus'])->name('store-management.status');

    // S10 — Store Wallet Management.
    Route::get('store-wallets', [StoreWalletManagementController::class, 'index'])->name('store-wallets.index');
    Route::get('store-wallets/{store}', [StoreWalletManagementController::class, 'show'])->name('store-wallets.show');
    Route::post('store-wallets/{store}/topup', [StoreWalletManagementController::class, 'topup'])->name('store-wallets.topup');

    // Admin Member Management.
    Route::get('members', [MemberManagementController::class, 'index'])->name('members.index');
    Route::get('members/export', [MemberManagementController::class, 'export'])->name('members.export');
    Route::get('members/{member}', [MemberManagementController::class, 'show'])->name('members.show');
    Route::patch('members/{member}', [MemberManagementController::class, 'update'])->name('members.update');

    // Admin Compensation Management.
    Route::get('compensation/config', [CompensationManagementController::class, 'config'])->name('compensation.config');
    Route::get('compensation/audit', [CompensationManagementController::class, 'audit'])->name('compensation.audit');

    // T-109 — Payout Requests queue (ProcessPayoutRequest/FailPayoutRequest/RejectPayoutRequest have existed since T-009 with no page wired to them).
    Route::get('payout-requests', [PayoutRequestController::class, 'index'])->name('payout-requests.index');
    Route::post('payout-requests/{payout_request}/process', [PayoutRequestController::class, 'process'])->name('payout-requests.process');
    Route::post('payout-requests/{payout_request}/fail', [PayoutRequestController::class, 'fail'])->name('payout-requests.fail');
    Route::post('payout-requests/{payout_request}/reject', [PayoutRequestController::class, 'reject'])->name('payout-requests.reject');

    // T-109 — Profile Change Requests queue (Approve/RejectProfileChangeRequest have existed since T-012 with no page wired to them).
    Route::get('profile-change-requests', [ProfileChangeRequestController::class, 'index'])->name('profile-change-requests.index');
    Route::post('profile-change-requests/{profile_change_request}/approve', [ProfileChangeRequestController::class, 'approve'])->name('profile-change-requests.approve');
    Route::post('profile-change-requests/{profile_change_request}/reject', [ProfileChangeRequestController::class, 'reject'])->name('profile-change-requests.reject');

    // T-018 — Reports (full cross-role, cross-module catalog + queued exports).
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::post('reports', [ReportsController::class, 'store'])->name('reports.store');
    Route::get('reports/{export}/download', [ReportsController::class, 'download'])->name('reports.download');
});
