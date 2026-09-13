<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §6 (Level Income) and §15 (Purchase/Repurchase Upline
     * Income) share the same shape — an eligible amount × a Sponsor/Direct
     * level rate, paid to one beneficiary per level — so both live in one
     * table with a `type` discriminator, distinct from Store Profit
     * Distribution (owner + 3 fixed levels, a different shape — see
     * `store_profit_distributions` in the store-sales migration). Whether
     * both ever fire on the same store transaction is still open — see
     * DOMAIN_LOGIC.md §21.
     *
     * `source_store_sale_id` intentionally has no FK constraint here: the
     * `store_sales` table is created in a later migration. The constraint is
     * added there once both tables exist.
     *
     * `eligibility_status`/`skip_reason` implement DOMAIN_LOGIC.md §6.2: "If a
     * member is not eligible, record a skipped/non-payable reason for
     * auditability" rather than silently omitting the row.
     */
    public function up(): void
    {
        Schema::create('income_ledger_calculations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['level_income', 'purchase_repurchase']);
            $table->foreignId('source_payment_id')->nullable()->constrained('payments');
            $table->unsignedBigInteger('source_store_sale_id')->nullable();
            $table->foreignId('beneficiary_member_id')->constrained('members');
            $table->unsignedTinyInteger('level_no')->nullable(); // 1-12
            $table->decimal('rate_percent', 6, 3);
            $table->decimal('amount', 14, 2);
            $table->foreignId('rule_version_id')->constrained('rule_versions');
            $table->enum('eligibility_status', ['paid', 'skipped'])->default('paid');
            $table->string('skip_reason')->nullable();
            $table->timestamps();

            $table->index(['beneficiary_member_id', 'type']);
            $table->index('source_store_sale_id');
        });

        Schema::create('pair_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('side', ['left', 'right']);
            $table->foreignId('source_payment_id')->constrained('payments');
            $table->enum('status', ['unused', 'consumed'])->default('unused');
            $table->unsignedInteger('consumed_for_milestone_no')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'side', 'status']);
        });

        Schema::create('pair_reward_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedInteger('milestone_no');
            $table->unsignedInteger('left_consumed_count');
            $table->unsignedInteger('right_consumed_count');
            $table->decimal('reward_amount', 14, 2);
            $table->foreignId('rule_version_id')->constrained('rule_versions');
            $table->date('calculated_for_month');
            $table->timestamps();

            $table->unique(['member_id', 'milestone_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pair_reward_transactions');
        Schema::dropIfExists('pair_entries');
        Schema::dropIfExists('income_ledger_calculations');
    }
};
