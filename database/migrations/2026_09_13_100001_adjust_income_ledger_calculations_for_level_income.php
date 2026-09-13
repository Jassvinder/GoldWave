<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gap discovered while implementing T-006 (Level Income), not a
     * business-rule change — see DOMAIN_LOGIC.md §21 discipline:
     *
     * `income_ledger_calculations.beneficiary_member_id` was created NOT NULL
     * in T-002, but DOMAIN_LOGIC.md §6.1 point 9 / Docs/TEST.md scenario 1's
     * edge case require a `skipped` row even when the Sponsor/Direct chain is
     * too short to reach a given level (no member exists at that level at
     * all, not just an ineligible one) — there is no beneficiary to attach in
     * that case. Relaxed to nullable.
     *
     * Also adds a DB-level uniqueness guard (`source_payment_id`, `level_no`)
     * per ARCHITECTURE.md's Compensation Engine point 4 ("idempotency
     * enforced at the data layer, not just in application logic") — a
     * retried/duplicate PaymentConfirmed dispatch can never double-create a
     * level's income row for the same payment.
     */
    public function up(): void
    {
        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->unsignedBigInteger('beneficiary_member_id')->nullable()->change();
        });

        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->unique(['source_payment_id', 'level_no'], 'income_ledger_calculations_payment_level_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->dropUnique('income_ledger_calculations_payment_level_unique');
        });

        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->unsignedBigInteger('beneficiary_member_id')->nullable(false)->change();
        });
    }
};
