<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.5: each store's stock is tracked item-wise, not
     * just a running total weight/value. `weight`/`price` are per-unit
     * (§16.5's fields read naturally as "one unit of this item weighs X,
     * costs Y" — a store with 10 identical 5g rings is one row with
     * quantity=10, not 10 separate rows). Stock enters via Super Admin's
     * jewellery allocation (`AllocateStoreInventoryItem`, §16.1) or an Item
     * Buyback (`RecordItemBuyback`, §16.7); a confirmed sale (§16.2/§16.8)
     * decrements it.
     */
    public function up(): void
    {
        Schema::create('store_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('item_name');
            $table->enum('metal', ['gold', 'silver']);
            $table->decimal('weight', 10, 3);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 14, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'metal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_inventory_items');
    }
};
