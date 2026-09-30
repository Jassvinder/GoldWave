<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOMAIN_LOGIC.md §8.3 (30-09-2026, user decision) — a draw month can pick more than one winner. The month's prize
 * row carries how many (`winners_count`, default 1), and each winner of a month is its own `draw_executions` row,
 * numbered by `winner_no`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_group_month_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('winners_count')->default(1)->after('metal_type');
        });

        Schema::table('draw_executions', function (Blueprint $table) {
            $table->unsignedTinyInteger('winner_no')->default(1)->after('cycle_month_no');
            $table->dropUnique(['draw_group_id', 'cycle_month_no']);
            $table->unique(['draw_group_id', 'cycle_month_no', 'winner_no']);
        });
    }

    public function down(): void
    {
        Schema::table('draw_executions', function (Blueprint $table) {
            $table->dropUnique(['draw_group_id', 'cycle_month_no', 'winner_no']);
            $table->unique(['draw_group_id', 'cycle_month_no']);
            $table->dropColumn('winner_no');
        });

        Schema::table('draw_group_month_configs', function (Blueprint $table) {
            $table->dropColumn('winners_count');
        });
    }
};
