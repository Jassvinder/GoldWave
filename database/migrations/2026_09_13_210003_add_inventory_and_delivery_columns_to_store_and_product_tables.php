<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.8: a confirmed sale identifies which
     * `store_inventory_items` row and how many units it draws stock from, so
     * `ConfirmStoreSale` can decrement it atomically and block on
     * insufficient stock — the same pattern already used for Store Wallet
     * deductions (§16.1). `quantity` defaults to 1 (a single-unit sale is the
     * common case; the column exists for confirmed sales of more than one
     * unit of the same item).
     *
     * DOMAIN_LOGIC.md §16.10: `product_benefits.store_id`/`delivered_at` let
     * `RecordPlanJewelleryDelivery` attribute a plan entitlement's physical
     * handover to a specific store — only once that's recorded does §16.4
     * scenario 3's Store Profit Distribution fire for it. A `product_benefits`
     * row delivered outside any store (or not yet delivered) simply leaves
     * both columns null, per that section's own conditional wording.
     */
    public function up(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->foreignId('store_inventory_item_id')->nullable()->after('store_id')
                ->constrained('store_inventory_items');
            $table->unsignedInteger('quantity')->default(1)->after('item_weight');
        });

        Schema::table('product_benefits', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('member_id')->constrained('stores');
            $table->timestamp('delivered_at')->nullable()->after('entry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_benefits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn('delivered_at');
        });

        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_inventory_item_id');
            $table->dropColumn('quantity');
        });
    }
};
