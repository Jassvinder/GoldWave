<?php

use App\Http\Controllers\Registration\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('join')->name('registration.')->group(function () {
    Route::get('/', [RegistrationController::class, 'show'])->name('show');
    Route::post('/validate-sponsor', [RegistrationController::class, 'validateSponsor'])->name('validate-sponsor');
    Route::post('/', [RegistrationController::class, 'store'])->name('store');
    Route::get('/status/{member}', [RegistrationController::class, 'status'])
        ->name('status')
        ->middleware('signed');
});
