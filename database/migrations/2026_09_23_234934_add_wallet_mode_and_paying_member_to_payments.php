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
     * DOMAIN_LOGIC.md §12.2(b) — T-153, Assisted Registration. A logged-in
     * Member or Store can fund a *different, new* member's registration
     * from their own wallet instead of the new member paying cash/online
     * themselves — `mode = 'wallet'`, settled instantly (unlike `cash`,
     * never `pending`). `paying_member_id` mirrors T-151's
     * `paying_store_id`: exactly one of the two is set for a `wallet`
     * payment, distinct from the row's own `member_id` (the person being
     * registered, never the payer).
     *
     * Postgres's `enum()` column is a `varchar` + CHECK constraint, not a
     * native enum type — `enum()->change()` generates invalid syntax on
     * Postgres (same issue already documented in
     * `add_purchase_repurchase_income_to_wallet_ledger_category`, confirmed
     * again directly against this table before writing this migration), so
     * the constraint is dropped/re-added directly instead, matching that
     * migration's own driver-conditional pattern (SQLite, used by the test
     * suite, has no named CHECK constraint to drop — `enum()->change()`
     * still works fine there).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('paying_member_id')->nullable()->after('paying_store_id')->constrained('members');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_mode_check');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_mode_check CHECK (mode IN ('online', 'cash', 'wallet'))");

            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('mode', ['online', 'cash', 'wallet'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_mode_check');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_mode_check CHECK (mode IN ('online', 'cash'))");
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->enum('mode', ['online', 'cash'])->change();
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paying_member_id');
        });
    }
};
