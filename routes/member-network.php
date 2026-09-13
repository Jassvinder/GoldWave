<?php

use App\Http\Controllers\Member\DirectsController;
use App\Http\Controllers\Member\TreeController;
use Illuminate\Support\Facades\Route;

/**
 * DOMAIN_LOGIC.md §4.1/§4.2 — Directs View and Tree View. Both roles that
 * can use these (member, super_admin) are allowed at the route level;
 * MemberPolicy narrows a plain member to their own downline while Super
 * Admin can open any member (DOMAIN_LOGIC.md §4.1/§4.2's "Super Admin can
 * open this view for any member"). `search` routes are registered before
 * the `{member?}` routes so the literal path segment "search" is never
 * swallowed by the optional model-binding parameter.
 */
Route::middleware(['auth', 'role:member,super_admin'])->prefix('member')->name('member.')->group(function () {
    Route::get('directs/search', [DirectsController::class, 'search'])->name('directs.search');
    Route::get('directs/{member?}', [DirectsController::class, 'show'])->name('directs.show');

    Route::get('tree/search', [TreeController::class, 'search'])->name('tree.search');
    Route::get('tree/{member?}', [TreeController::class, 'show'])->name('tree.show');
});
