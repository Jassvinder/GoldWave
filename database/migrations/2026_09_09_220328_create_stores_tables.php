<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.1: each store has a dedicated Store Wallet funded by an
     * initial advance and Super Admin-confirmed top-ups; deductions must be atomic
     * and auditable, so the wallet ledger is a separate append-only table (mirrors
     * the member wallet_ledger_entries design — never mutate a balance without a
     * ledger row).
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Store login (T-131): GWLST0001… code, plus its own password once an owner is assigned.
            $table->string('store_code')->nullable()->unique();
            $table->string('password')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->decimal('jewellery_allocation_value', 14, 2)->default(0);
            $table->decimal('advance_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('store_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained('stores')->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
        });

        Schema::create('store_wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_wallet_id')->constrained('store_wallets')->cascadeOnDelete();
            $table->enum('type', ['advance_credit', 'topup', 'deduction']);
            $table->decimal('amount', 14, 2);
            $table->string('reference')->unique();
            $table->foreignId('operator_user_id')->constrained('users');
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_wallet_id', 'occurred_at']);
        });

        // DOMAIN_LOGIC.md §16.5: each store's stock is tracked item-wise. `weight`/`price` are per unit (10 identical
        // 5 g rings = one row with quantity 10). Stock enters via Super Admin's allocation or an Item Buyback; a
        // confirmed sale decrements it.
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
        Schema::dropIfExists('store_wallet_ledger_entries');
        Schema::dropIfExists('store_wallets');
        Schema::dropIfExists('stores');
    }
};
