<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-169 (28-09-2026, user decision — DOMAIN_LOGIC.md §21 "28-09-2026
 * feedback batch"): a store sale is priced by the server at the current rate
 * (weight × rate + making %, then GST from the Super Admin setting), and the
 * store can no longer type the amount. These columns snapshot that pricing so
 * every bill and every income calculation stays reproducible. `metal_value`
 * is also the base for Store Profit Distribution and Purchase/Repurchase
 * income. All nullable: rows recorded before this change keep their typed
 * `sale_amount`, which remains their income base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->foreignId('metal_rate_id')->nullable()->after('rate')->constrained('metal_rates');
            $table->decimal('metal_value', 14, 2)->nullable()->after('metal_rate_id');
            $table->decimal('making_charge_percent', 5, 2)->nullable()->after('metal_value');
            $table->decimal('making_charges', 14, 2)->nullable()->after('making_charge_percent');
            $table->decimal('gst_percent', 5, 2)->nullable()->after('making_charges');
        });
    }

    public function down(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('metal_rate_id');
            $table->dropColumn(['metal_value', 'making_charge_percent', 'making_charges', 'gst_percent']);
        });
    }
};
