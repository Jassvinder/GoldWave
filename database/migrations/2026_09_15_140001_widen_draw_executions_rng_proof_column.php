<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real production-scale bug, found only by seeding a demo network
     * large enough to actually form a full 200-member draw group and
     * execute it (`DOMAIN_LOGIC.md` §21) — every prior automated test used
     * a small group size (3-10 members) for speed, so this never surfaced.
     * `rng_proof` (DOMAIN_LOGIC.md §8.4 point 4's "auditable random-
     * selection record") is a JSON blob containing every eligible member's
     * ID in the group's pool — at the real `draw_group_size` default (200),
     * this comfortably exceeds `varchar(255)`, and `ExecuteMonthlyDraw`
     * would fail with a real "value too long" database error the moment a
     * production-sized group actually executed. Widened to `text`.
     */
    public function up(): void
    {
        Schema::table('draw_executions', function (Blueprint $table) {
            $table->text('rng_proof')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('draw_executions', function (Blueprint $table) {
            $table->string('rng_proof')->nullable()->change();
        });
    }
};
