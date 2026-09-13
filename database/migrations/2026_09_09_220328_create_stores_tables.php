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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_wallet_ledger_entries');
        Schema::dropIfExists('store_wallets');
        Schema::dropIfExists('stores');
    }
};
