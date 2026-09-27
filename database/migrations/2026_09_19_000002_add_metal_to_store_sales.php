<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-110 (19-09-2026) — Gold/Silver compensation split surfaced a real,
 * pre-existing gap: `ConfirmStoreSale` already supports a sale with no
 * tracked `store_inventory_item_id` (a manual/custom item entry, or
 * `RecordPlanJewelleryDelivery`'s plan-based delivery) — for that case,
 * nothing anywhere records which metal the sale was, so
 * `CalculatePurchaseRepurchaseIncome`/`CalculateStoreProfitDistribution`
 * have no way to pick the Gold or Silver rate table. `store_sales.metal` is
 * the single source of truth going forward: set once at confirmation
 * (`ConfirmStoreSale`, from the linked inventory item when there is one,
 * otherwise an explicit caller-supplied value), read directly by both
 * compensation Actions rather than each re-deriving it via a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->enum('metal', ['gold', 'silver'])->nullable()->after('item_name');
        });

        // Every real row today has a tracked inventory item to backfill
        // from (verified against the real dev database before writing
        // this migration) — a manual/no-inventory sale with no derivable
        // metal has never actually happened yet.
        DB::table('store_sales as ss')
            ->join('store_inventory_items as sii', 'sii.id', '=', 'ss.store_inventory_item_id')
            ->select('ss.id', 'sii.metal')
            ->get()
            ->each(fn ($row) => DB::table('store_sales')->where('id', $row->id)->update(['metal' => $row->metal]));
    }

    public function down(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropColumn('metal');
        });
    }
};
