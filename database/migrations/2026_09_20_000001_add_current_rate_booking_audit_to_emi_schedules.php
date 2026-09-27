<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-116 (20-09-2026) — every EMI schedule now starts on Future Rate and may be switched to Current Rate once, after
     * registration. These three nullable columns record the switch (when, and how many EMIs / how much money had already
     * been paid and credited), so together with the existing rate/weight/maintenance snapshot the new EMI amount can
     * always be reproduced. All null for a schedule that was never switched (and for schedules registered directly on
     * Current Rate before this change).
     */
    public function up(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->timestamp('current_rate_booked_at')->nullable()->after('future_commitment_amount');
            $table->unsignedSmallInteger('installments_paid_at_booking')->nullable()->after('current_rate_booked_at');
            $table->decimal('amount_paid_at_booking', 14, 2)->nullable()->after('installments_paid_at_booking');
        });
    }

    public function down(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->dropColumn(['current_rate_booked_at', 'installments_paid_at_booking', 'amount_paid_at_booking']);
        });
    }
};
