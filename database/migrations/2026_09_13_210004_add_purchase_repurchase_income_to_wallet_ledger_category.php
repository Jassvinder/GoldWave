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
     * Gap discovered while implementing T-014 (Store module), not a
     * business-rule change — see DOMAIN_LOGIC.md §21 discipline:
     * `wallet_ledger_entries.category`'s enum already anticipated
     * `store_distribution` (used by `CalculateStoreProfitDistribution`,
     * DOMAIN_LOGIC.md §16.4) since T-002's up-front schema pass, but never
     * anticipated a distinct category for Purchase/Repurchase Upline Income
     * (§15) — added here so `CalculatePurchaseRepurchaseIncome`'s wallet
     * credits are distinguishable from Level Income in the ledger.
     *
     * Laravel's `enum()->change()` generates invalid Postgres syntax (an
     * inline CHECK on an ALTER COLUMN TYPE clause) — confirmed by actually
     * running this migration against the real Postgres dev DB, not just the
     * SQLite test suite. Postgres's `enum()` column is a `varchar` + CHECK
     * constraint (no native ENUM type), so the constraint is dropped and
     * recreated directly instead; SQLite has no named CHECK constraint to
     * drop, so a plain column rebuild via `change()` still works there.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallet_ledger_entries DROP CONSTRAINT wallet_ledger_entries_category_check');
            DB::statement("ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_category_check CHECK (category IN ('level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout', 'purchase_repurchase_income'))");

            return;
        }

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->enum('category', [
                'level_income', 'pair_reward', 'booster', 'draw_benefit',
                'store_distribution', 'payout', 'purchase_repurchase_income',
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
            DB::statement("ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_category_check CHECK (category IN ('level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout'))");

            return;
        }

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->enum('category', ['level_income', 'pair_reward', 'booster', 'draw_benefit', 'store_distribution', 'payout'])->change();
        });
    }
};
