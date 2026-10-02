<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-183 (30-09-2026, DOMAIN_LOGIC.md §15) — the moment a member first had `store_income_min_directs` qualified directs.
 * Set once, never cleared: from then on they earn the upline parts of store income for life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('store_income_unlocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('store_income_unlocked_at');
        });
    }
};
