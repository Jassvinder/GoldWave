<?php

use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\User;
use Illuminate\Support\Facades\Process;

/**
 * T-020 (DOMAIN_LOGIC.md §21) — real, separate-OS-process concurrency
 * tests against the actual dev Postgres database. Never uses
 * RefreshDatabase (that would wipe real dev data) — each test creates its
 * own clearly-named throwaway fixtures and deletes them itself, win or
 * lose, the same discipline this project's manual QA passes already use.
 *
 * Run explicitly: php artisan test --configuration=phpunit.concurrency.xml
 */
test('two simultaneous Store Wallet deductions that together exceed the balance never both succeed', function () {
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $store = Store::create([
        'name' => 'Concurrency Test Store — Wallet',
        'owner_user_id' => null,
        'status' => 'active',
        'jewellery_allocation_value' => 0,
        'advance_amount' => 0,
    ]);

    $wallet = StoreWallet::create([
        'store_id' => $store->id,
        'balance' => 1000,
        'status' => 'active',
    ]);

    try {
        $base = base_path();

        $processes = collect(range(1, 3))->map(function () use ($base, $wallet, $operator) {
            return Process::path($base)
                ->start("php artisan concurrency:deduct-store-wallet {$wallet->id} 400 {$operator->id}");
        });

        $results = $processes->map(fn ($process) => $process->wait());

        $successCount = $results->filter(fn ($r) => str_contains($r->output(), 'SUCCESS'))->count();
        $blockedCount = $results->filter(fn ($r) => str_contains($r->output(), 'BLOCKED'))->count();

        expect($successCount)->toBe(2);
        expect($blockedCount)->toBe(1);
        expect((float) $wallet->fresh()->balance)->toBe(200.0);
    } finally {
        $wallet->ledgerEntries()->delete();
        $wallet->delete();
        $store->delete();
    }
});
