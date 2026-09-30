<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-184 (30-09-2026, DOMAIN_LOGIC.md §5 point 9) — a "Pay All Remaining EMIs" payment records how many EMIs it
 * settles. Null on every other payment, so a full payment is told apart from a single EMI even when one EMI is left
 * (on Current Rate the full payment excludes maintenance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('covers_installments')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('covers_installments');
        });
    }
};
