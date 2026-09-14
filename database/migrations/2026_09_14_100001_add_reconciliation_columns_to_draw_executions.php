<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-017 pre-coding pass, DOMAIN_LOGIC.md §21: closing metadata for the
     * already-present but never-used `status='reconciled'` state — never the
     * immutable result fields (`winner_member_id`/`rng_proof`/
     * `upline_benefit_member_id`), which this task's `ReconcileDrawExecution`
     * Action never touches.
     */
    public function up(): void
    {
        Schema::table('draw_executions', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable()->after('status');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('draw_executions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn('reconciled_at');
        });
    }
};
