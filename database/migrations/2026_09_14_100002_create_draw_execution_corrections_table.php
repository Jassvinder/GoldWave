<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-017 pre-coding pass, DOMAIN_LOGIC.md §21/§8.6: the literal
     * "reversal/correction audit record" §8.6 requires when an admin
     * corrects a draw — a separate, append-only table rather than editing
     * `draw_executions` in place, so draw history is never overwritten.
     */
    public function up(): void
    {
        Schema::create('draw_execution_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_execution_id')->constrained('draw_executions')->cascadeOnDelete();
            $table->text('note');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_execution_corrections');
    }
};
