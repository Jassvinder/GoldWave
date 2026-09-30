<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-185c (30-09-2026, DOMAIN_LOGIC.md §16.13 point 4) — a store sale that hands over a Repurchase on EMI (the fully paid
 * piece, or the silver owed after a break) points to its booking and records how much of the bill was already paid
 * through the EMIs (`prepaid_amount`); the member pays only the rest at the counter. Such a sale never generates income.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->foreignId('store_emi_booking_id')->nullable()->constrained('store_emi_bookings');
            $table->decimal('prepaid_amount', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_emi_booking_id');
            $table->dropColumn('prepaid_amount');
        });
    }
};
