<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/registration.php';
require __DIR__.'/goldwave-auth.php';
require __DIR__.'/payments.php';
require __DIR__.'/super-admin.php';
require __DIR__.'/member-network.php';
require __DIR__.'/member-emi.php';
