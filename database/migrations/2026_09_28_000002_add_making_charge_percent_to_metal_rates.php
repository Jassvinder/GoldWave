<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-165 (28-09-2026, user decision — DOMAIN_LOGIC.md §21 "28-09-2026
 * feedback batch"): Super Admin enters the rate per 10 gm together with a
 * making-charges percentage, separately for Gold and Silver. The percentage
 * lives on the same effective-dated row as the rate, so every sale/booking
 * that picks up "the current rate" also picks up the making % that applied
 * with it. The rate itself is still stored per gram (entered value ÷ 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_rates', function (Blueprint $table) {
            $table->decimal('making_charge_percent', 5, 2)->default(0)->after('rate_per_gram');
        });
    }

    public function down(): void
    {
        Schema::table('metal_rates', function (Blueprint $table) {
            $table->dropColumn('making_charge_percent');
        });
    }
};
