<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §3/§3.0 (client spec v2.0, resolved 12-09-2026) — the
     * pending schema change flagged in DATABASE_SCHEMA.md's Open Items,
     * applied now that T-003 has the user's go-ahead to implement
     * registration. Adds:
     *
     * - `membership_plans.fixed_weight_grams` — the fixed jewellery weight
     *   for EMI plans A-D (null for one-time plans E/F, which have no fixed
     *   weight — the member chooses jewellery at the rate on delivery date).
     * - `emi_schedules` rate-booking columns — the member's mandatory
     *   Current-Rate-vs-Future-Rate choice (§3.0) and, for Current Rate
     *   Booking, a full snapshot of the rate/weight/maintenance-cost used so
     *   the calculation stays reproducible even if the metal rate changes
     *   later (§22). `installment_amount` is always populated (both booking
     *   methods produce one); the rate-specific columns are null for
     *   Future Rate Booking, and `future_commitment_amount` is null for
     *   Current Rate Booking.
     */
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->decimal('fixed_weight_grams', 8, 3)->nullable()->after('product_category');
        });

        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->enum('rate_booking_method', ['current_rate', 'future_rate'])->after('total_installments');
            $table->decimal('installment_amount', 14, 2)->after('rate_booking_method');

            // Current Rate Booking snapshot (null for Future Rate Booking)
            $table->foreignId('metal_rate_id')->nullable()->after('installment_amount')->constrained('metal_rates');
            $table->decimal('rate_per_gram_at_booking', 14, 2)->nullable()->after('metal_rate_id');
            $table->decimal('fixed_weight_grams', 8, 3)->nullable()->after('rate_per_gram_at_booking');
            $table->decimal('maintenance_cost', 14, 2)->nullable()->after('fixed_weight_grams');
            $table->foreignId('rule_version_id')->nullable()->after('maintenance_cost')->constrained('rule_versions');

            // Future Rate Booking commitment (null for Current Rate Booking)
            $table->decimal('future_commitment_amount', 14, 2)->nullable()->after('rule_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rule_version_id');
            $table->dropConstrainedForeignId('metal_rate_id');
            $table->dropColumn([
                'rate_booking_method',
                'installment_amount',
                'rate_per_gram_at_booking',
                'fixed_weight_grams',
                'maintenance_cost',
                'future_commitment_amount',
            ]);
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('fixed_weight_grams');
        });
    }
};
