<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §9: qualification is required only once, then the
     * member receives the benefit for 6 consecutive months (client spec v2.0,
     * updated from 3) regardless of whether they keep meeting the threshold —
     * so `booster_qualifications` records the one-time qualifying event, and
     * `booster_payout_schedules` holds the resulting 6 scheduled payouts.
     */
    public function up(): void
    {
        Schema::create('booster_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedTinyInteger('level_no');
            $table->timestamp('qualified_at');
            $table->foreignId('rule_version_id')->constrained('rule_versions');
            $table->timestamps();
        });

        Schema::create('booster_payout_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booster_qualification_id')->constrained('booster_qualifications')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_no'); // 1-6
            $table->date('scheduled_date');
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->foreignId('wallet_ledger_entry_id')->nullable()->constrained('wallet_ledger_entries');
            $table->timestamps();

            $table->unique(['booster_qualification_id', 'month_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booster_payout_schedules');
        Schema::dropIfExists('booster_qualifications');
    }
};
