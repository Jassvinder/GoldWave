<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §12: the wallet is the financial truth layer. Rule:
     * "never update a wallet balance without an associated ledger transaction."
     * `source_type`/`source_id` is a polymorphic reference so one table can
     * link back to whichever module produced the credit/debit (Level Income,
     * Pair/Reward, Booster, Draw benefit, Store Distribution, Payout) without
     * a nullable FK column per source type.
     */
    public function up(): void
    {
        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('entry_type', ['credit', 'debit']);
            $table->enum('category', ['level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout']);
            $table->nullableMorphs('source');
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'confirmed', 'reversed'])->default('confirmed');
            $table->text('description')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
    }
};
