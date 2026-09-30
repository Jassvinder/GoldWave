<?php

use App\Http\Controllers\Admin\AssistedRegistrationController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\StoreEmiBookingController;
use App\Http\Controllers\Admin\StoreProfileController;
use App\Http\Controllers\Admin\StoreReportsController;
use App\Http\Controllers\Admin\StoreTransactionsController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/**
 * INSTRUCTIONS.md's Admin / Store Owner Portal (T-016) — A02-A06. A01 (Store
 * Dashboard) is the shared `/dashboard` route (`DashboardController`
 * delegates to `Admin\StoreDashboardController` for a role=admin user with
 * an assigned store), matching M01's precedent from T-015.
 */
Route::middleware(['auth', 'role:store_admin', 'store-owner'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->defaults('portal', 'admin')->name('notifications.index');

    // T-153 — Assisted Registration (DOMAIN_LOGIC.md §12.2(b)).
    Route::get('register-new', [AssistedRegistrationController::class, 'show'])->name('assisted-registration.show');
    Route::post('register-new', [AssistedRegistrationController::class, 'store'])->name('assisted-registration.store');

    Route::get('profile', [StoreProfileController::class, 'show'])->name('profile.show');
    Route::post('profile', [StoreProfileController::class, 'update'])->name('profile.update');

    Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
    Route::post('sales', [SalesController::class, 'storeSale'])->name('sales.store');
    Route::post('sales/buyback', [SalesController::class, 'storeBuyback'])->name('sales.buyback');
    Route::post('sales/delivery', [SalesController::class, 'storeDelivery'])->name('sales.delivery');
    Route::post('sales/collect-payment/{payment}', [SalesController::class, 'collectPayment'])->name('sales.collect-payment');
    Route::get('sales/{sale}/invoice', [SalesController::class, 'invoice'])->name('sales.invoice');
    Route::post('sales/{sale}/bill', [SalesController::class, 'generateBill'])->name('sales.bill');

    // T-185a — Repurchase on EMI requests.
    Route::get('store-emi', [StoreEmiBookingController::class, 'index'])->name('store-emi.index');
    Route::post('store-emi', [StoreEmiBookingController::class, 'store'])->name('store-emi.store');
    Route::post('store-emi/{booking}/deliver-piece', [StoreEmiBookingController::class, 'deliverPiece'])->name('store-emi.deliver-piece');
    Route::post('store-emi/{booking}/deliver-silver', [StoreEmiBookingController::class, 'deliverSilver'])->name('store-emi.deliver-silver');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/restock/{shipment}/received', [InventoryController::class, 'markRestockReceived'])->name('inventory.restock.received');

    Route::get('transactions', [StoreTransactionsController::class, 'index'])->name('transactions.index');

    Route::get('reports', [StoreReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/export', [StoreReportsController::class, 'download'])->name('reports.export');
});
