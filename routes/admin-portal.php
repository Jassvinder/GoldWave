<?php

use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\StoreProfileController;
use App\Http\Controllers\Admin\StoreReportsController;
use App\Http\Controllers\Admin\StoreTransactionsController;
use Illuminate\Support\Facades\Route;

/**
 * INSTRUCTIONS.md's Admin / Store Owner Portal (T-016) — A02-A06. A01 (Store
 * Dashboard) is the shared `/dashboard` route (`DashboardController`
 * delegates to `Admin\StoreDashboardController` for a role=admin user with
 * an assigned store), matching M01's precedent from T-015.
 */
Route::middleware(['auth', 'role:admin', 'store-owner'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('profile', [StoreProfileController::class, 'show'])->name('profile.show');
    Route::post('profile', [StoreProfileController::class, 'update'])->name('profile.update');

    Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
    Route::post('sales', [SalesController::class, 'storeSale'])->name('sales.store');
    Route::post('sales/buyback', [SalesController::class, 'storeBuyback'])->name('sales.buyback');
    Route::post('sales/delivery', [SalesController::class, 'storeDelivery'])->name('sales.delivery');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

    Route::get('transactions', [StoreTransactionsController::class, 'index'])->name('transactions.index');

    Route::get('reports', [StoreReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}/export', [StoreReportsController::class, 'download'])->name('reports.export');
});
