<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.7: a Buyback is the store buying an item back from
     * the member who owns it, at a Super Admin-configurable percentage of
     * the item's *current market rate* — a separate, distinct transaction
     * type from a `store_sales` row, since it moves in the opposite
     * direction and (§16.9) never triggers Store Profit Distribution or
     * Purchase/Repurchase Upline Income. `buyback_percent`/
     * `rate_per_gram_at_buyback`/`rule_version_id` are all snapshotted at the
     * moment of the buyback (DOMAIN_LOGIC.md §22 reproducibility), matching
     * the pattern already used for EMI Rate Booking and Store Profit
     * Distribution. `resulting_inventory_item_id` links to the
     * `store_inventory_items` row the bought-back item was added/returned to.
     */
    public function up(): void
    {
        Schema::create('store_buybacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('member_id')->constrained('members');
            $table->string('item_name');
            $table->enum('metal', ['gold', 'silver']);
            $table->decimal('weight', 10, 3);
            $table->unsignedInteger('quantity')->default(1);
            $table->text('description')->nullable();
            $table->foreignId('metal_rate_id')->constrained('metal_rates');
            $table->decimal('rate_per_gram_at_buyback', 14, 2);
            $table->decimal('buyback_percent', 6, 3);
            $table->decimal('price_paid', 14, 2);
            $table->foreignId('resulting_inventory_item_id')->constrained('store_inventory_items');
            $table->foreignId('rule_version_id')->constrained('rule_versions');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'occurred_at']);
            $table->index('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_buybacks');
    }
};
