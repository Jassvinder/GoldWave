<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-237 (DOMAIN_LOGIC.md §3.3, 18-10-2026) — Membership Plans become editable by Super Admin.
     *
     * A plan edit must never change an existing member, so each membership EMI schedule keeps the plan's EMI amount and
     * Current Rate Booking weight as they were when the member joined. Existing schedules are back-filled from their plan:
     * exact, because no plan had ever been edited before this migration. `membership_plan_changes` records every create
     * and edit made on the Membership Plans page.
     *
     * Forward-only (08-10-2026 rule): the live database is in use.
     */
    public function up(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->decimal('plan_installment_amount', 14, 2)->nullable();
            $table->decimal('plan_fixed_weight_grams', 8, 3)->nullable();
        });

        DB::table('emi_schedules')
            ->where('kind', 'membership')
            ->whereNotNull('membership_plan_id')
            ->orderBy('id')
            ->each(function (object $schedule) {
                $plan = DB::table('membership_plans')->where('id', $schedule->membership_plan_id)->first();

                if ($plan === null) {
                    return;
                }

                DB::table('emi_schedules')->where('id', $schedule->id)->update([
                    'plan_installment_amount' => $plan->amount,
                    'plan_fixed_weight_grams' => $plan->fixed_weight_grams,
                ]);
            });

        Schema::create('membership_plan_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained('membership_plans');
            $table->foreignId('changed_by')->constrained('users');
            $table->enum('action', ['created', 'updated']);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamps();

            $table->index(['membership_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_changes');

        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->dropColumn(['plan_installment_amount', 'plan_fixed_weight_grams']);
        });
    }
};
