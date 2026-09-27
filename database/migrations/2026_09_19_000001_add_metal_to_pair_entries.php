<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-110 (19-09-2026) — Gold/Silver compensation split. `pair_entries` pools
 * entries from many different joinings into one per-beneficiary count; since
 * each joining's own plan metal decides its `pair_value_per_entry` at
 * month-end consumption, every entry must record which metal it came from
 * at creation time — the milestone thresholds/counting themselves stay
 * unified/mixed (user's explicit instruction), only the per-entry reward
 * value differs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pair_entries', function (Blueprint $table) {
            $table->enum('metal', ['gold', 'silver'])->nullable()->after('side');
        });

        // Backfill existing rows from the joining's own plan metal, via the
        // entry's source payment — the exact same relation CreatePairEntries
        // itself will now use going forward. Done row-by-row in PHP rather
        // than a joined UPDATE, since UPDATE...JOIN isn't portable across
        // this project's Postgres (dev) and SQLite (test) drivers.
        DB::table('pair_entries')
            ->select('pair_entries.id', 'payments.member_id')
            ->join('payments', 'payments.id', '=', 'pair_entries.source_payment_id')
            ->orderBy('pair_entries.id')
            ->get()
            ->groupBy('member_id')
            ->each(function ($rows, $memberId) {
                $planCategory = DB::table('members')
                    ->join('membership_plans', 'membership_plans.id', '=', 'members.membership_plan_id')
                    ->where('members.id', $memberId)
                    ->value('membership_plans.product_category');

                if ($planCategory === null) {
                    return;
                }

                DB::table('pair_entries')
                    ->whereIn('id', $rows->pluck('id'))
                    ->update(['metal' => $planCategory]);
            });
    }

    public function down(): void
    {
        Schema::table('pair_entries', function (Blueprint $table) {
            $table->dropColumn('metal');
        });
    }
};
