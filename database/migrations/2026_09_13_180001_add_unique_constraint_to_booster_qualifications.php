<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §21 T-011 pre-coding pass: `booster_qualifications`
     * had no DB-level uniqueness guard on (member_id, level_no) — only
     * `booster_payout_schedules` did. Matches ARCHITECTURE.md's stated
     * idempotency-enforced-at-the-data-layer principle, already applied to
     * every other compensation table (`pair_entries`, `draw_executions`,
     * `payments`).
     */
    public function up(): void
    {
        Schema::table('booster_qualifications', function (Blueprint $table) {
            $table->unique(['member_id', 'level_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booster_qualifications', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'level_no']);
        });
    }
};
