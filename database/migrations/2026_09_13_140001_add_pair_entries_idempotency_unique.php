<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * T-007: one qualifying joining fans out into one `pair_entries` row per
     * Binary Position ancestor (DOMAIN_LOGIC.md §7's Pair/Reward Beneficiary
     * Chain Rule). Per ARCHITECTURE.md's idempotency principle, a retried
     * PaymentConfirmed dispatch must not double-create a given ancestor's row
     * for the same qualifying payment — enforced at the DB layer, not just in
     * `CreatePairEntries`.
     */
    public function up(): void
    {
        Schema::table('pair_entries', function (Blueprint $table) {
            $table->unique(['source_payment_id', 'member_id'], 'pair_entries_payment_member_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pair_entries', function (Blueprint $table) {
            $table->dropUnique('pair_entries_payment_member_unique');
        });
    }
};
