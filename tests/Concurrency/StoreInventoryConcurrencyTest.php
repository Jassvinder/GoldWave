<?php

use App\Models\Invoice;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreInventoryItem;
use App\Models\StoreSale;
use App\Models\User;
use Illuminate\Support\Facades\Process;

/**
 * T-020 (DOMAIN_LOGIC.md §21) — real, separate-OS-process concurrency test
 * against the actual dev Postgres database. Mirrors
 * tests/Concurrency/StoreConcurrencyTest.php's pattern exactly. Never uses
 * RefreshDatabase — creates and cleans up its own throwaway fixtures.
 *
 * Run explicitly: php artisan test --configuration=phpunit.concurrency.xml
 */
test('simultaneous store sales against tracked inventory never oversell stock', function () {
    $operator = User::where('role', 'super_admin')->firstOrFail();

    $store = Store::create([
        'name' => 'Concurrency Test Store — Inventory',
        'owner_user_id' => null,
        'status' => 'active',
        'jewellery_allocation_value' => 0,
        'advance_amount' => 0,
    ]);

    $item = StoreInventoryItem::create([
        'store_id' => $store->id,
        'item_name' => 'Concurrency Test Ring',
        'metal' => 'gold',
        'weight' => 5,
        'quantity' => 5,
        'price' => 6000,
    ]);

    try {
        $base = base_path();

        $processes = collect(range(1, 3))->map(function () use ($base, $store, $item, $operator) {
            return Process::path($base)
                ->start("php artisan concurrency:confirm-store-sale {$store->id} {$item->id} 2 {$operator->id}");
        });

        $results = $processes->map(fn ($process) => $process->wait());

        $successCount = $results->filter(fn ($r) => str_contains($r->output(), 'SUCCESS'))->count();
        $blockedCount = $results->filter(fn ($r) => str_contains($r->output(), 'BLOCKED'))->count();

        expect($successCount)->toBe(2);
        expect($blockedCount)->toBe(1);
        expect($item->fresh()->quantity)->toBe(1);
    } finally {
        $saleIds = StoreSale::where('store_id', $store->id)->pluck('id');
        Invoice::whereIn('store_sale_id', $saleIds)->delete();
        StoreSale::whereIn('id', $saleIds)->delete();
        StoreActivityLog::where('store_id', $store->id)->delete();
        $item->delete();
        $store->delete();
    }
});
