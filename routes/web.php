<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// T-140 — read-state endpoints for the header bell and the Notifications pages (own notifications only).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::get('{notification}/open', [NotificationController::class, 'open'])->name('open');
    Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
});

require __DIR__.'/settings.php';
require __DIR__.'/registration.php';
require __DIR__.'/goldwave-auth.php';
require __DIR__.'/payments.php';
require __DIR__.'/super-admin.php';
require __DIR__.'/member-network.php';
require __DIR__.'/member-emi.php';
require __DIR__.'/member-portal.php';
require __DIR__.'/admin-portal.php';
