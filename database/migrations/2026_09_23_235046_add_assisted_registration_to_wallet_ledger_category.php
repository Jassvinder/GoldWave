<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §12.2(b) — T-153. `wallet_ledger_entries.category`
     * gains `assisted_registration` — a member's own wallet debit when they
     * fund a *different, new* member's Assisted Registration. Same
     * driver-conditional drop/re-add pattern as the existing
     * `add_purchase_repurchase_income_to_wallet_ledger_category` migration
     * (Postgres's `enum()->change()` is invalid on a varchar+CHECK column;
     * SQLite, used by the test suite, has no named CHECK constraint to
     * drop, so `enum()->change()` still works fine there).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallet_ledger_entries DROP CONSTRAINT wallet_ledger_entries_category_check');
            DB::statement("ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_category_check CHECK (category IN ('level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout', 'purchase_repurchase_income', 'assisted_registration'))");

            return;
        }

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->enum('category', [
                'level_income', 'pair_reward', 'booster', 'draw_benefit',
                'store_distribution', 'payout', 'purchase_repurchase_income', 'assisted_registration',
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallet_ledger_entries DROP CONSTRAINT wallet_ledger_entries_category_check');
            DB::statement("ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_category_check CHECK (category IN ('level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout', 'purchase_repurchase_income'))");

            return;
        }

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->enum('category', [
                'level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout', 'purchase_repurchase_income',
            ])->change();
        });
    }
};
