<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-020 (DOMAIN_LOGIC.md §21) — a real schema gap a concurrency test's
     * own cleanup code surfaced, not a business-rule change: deleting a
     * Member with a *paid* booster payout schedule could fail with a
     * foreign key violation, because `wallet_ledger_entries.member_id`
     * cascades from `members` while `booster_payout_schedules
     * .wallet_ledger_entry_id` had no `ON DELETE` action at all (Postgres
     * default: RESTRICT) — the two cascade paths from the same `members`
     * row could race against each other during Postgres's own internal
     * FK-action ordering. Fixed by nulling the schedule's reference instead
     * of blocking the delete; the schedule row itself is already deleted a
     * moment later anyway via `booster_qualifications`' own cascade from
     * `members`, so this never leaves an orphaned, inconsistent row.
     */
    public function up(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE booster_payout_schedules DROP CONSTRAINT booster_payout_schedules_wallet_ledger_entry_id_foreign');
            DB::statement('ALTER TABLE booster_payout_schedules ADD CONSTRAINT booster_payout_schedules_wallet_ledger_entry_id_foreign FOREIGN KEY (wallet_ledger_entry_id) REFERENCES wallet_ledger_entries(id) ON DELETE SET NULL');

            return;
        }

        Schema::table('booster_payout_schedules', function ($table) {
            $table->dropForeign(['wallet_ledger_entry_id']);
            $table->foreign('wallet_ledger_entry_id')->references('id')->on('wallet_ledger_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE booster_payout_schedules DROP CONSTRAINT booster_payout_schedules_wallet_ledger_entry_id_foreign');
            DB::statement('ALTER TABLE booster_payout_schedules ADD CONSTRAINT booster_payout_schedules_wallet_ledger_entry_id_foreign FOREIGN KEY (wallet_ledger_entry_id) REFERENCES wallet_ledger_entries(id)');

            return;
        }

        Schema::table('booster_payout_schedules', function ($table) {
            $table->dropForeign(['wallet_ledger_entry_id']);
            $table->foreign('wallet_ledger_entry_id')->references('id')->on('wallet_ledger_entries');
        });
    }
};
