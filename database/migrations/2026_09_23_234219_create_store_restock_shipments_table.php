<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.12 — T-152. A store that delivers a member's plan
     * jewellery without ever receiving the money for it (paid online, paid
     * cash approved with no store involved, or paid cash but settled
     * through a *different* store, §12.2(a)) is owed a restock of the same
     * item from the company. Tracked as a two-step shipment, not an instant
     * credit: `owed` → `sent` (Super Admin ships it) → `received` (the
     * store — or Super Admin on its behalf — confirms arrival, which is the
     * point the item actually lands in `store_inventory_items`, via
     * `AllocateStoreInventoryItem`'s existing merge-or-create logic).
     * `product_benefit_id` links back to the specific delivery that created
     * this shipment; item fields are a snapshot at creation time, matching
     * this project's reproducibility rule (DOMAIN_LOGIC.md §22) — the
     * source `product_benefits`/store rows can change owner or be deleted
     * without corrupting this record's own meaning.
     */
    public function up(): void
    {
        Schema::create('store_restock_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('product_benefit_id')->nullable()->constrained('product_benefits');
            $table->string('item_name');
            $table->enum('metal', ['gold', 'silver']);
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('value', 14, 2);
            $table->enum('status', ['owed', 'sent', 'received'])->default('owed');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->foreignId('resulting_inventory_item_id')->nullable()->constrained('store_inventory_items');
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_restock_shipments');
    }
};
