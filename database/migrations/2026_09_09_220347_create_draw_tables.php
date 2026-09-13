<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §8: each generated group owns an independent 20-month
     * draw cycle (`draw_groups`), with a frozen member snapshot
     * (`draw_group_members`), a per-month prize configuration
     * (`draw_group_month_configs` — first 15 months Silver, final 5 Gold, but
     * configurable per group/month so historical prizes stay exact even if
     * settings change later), and one immutable execution record per month
     * (`draw_executions`, holding the RNG result and upline-benefit outcome).
     */
    public function up(): void
    {
        Schema::create('draw_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('group_no');
            $table->unsignedInteger('size');
            $table->date('cycle_started_month');
            $table->enum('status', ['forming', 'active', 'completed'])->default('forming');
            $table->timestamps();

            $table->unique(['group_no', 'cycle_started_month']);
        });

        Schema::create('draw_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_group_id')->constrained('draw_groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members');
            $table->boolean('is_winner_removed')->default(false);
            $table->timestamps();

            $table->unique(['draw_group_id', 'member_id']);
        });

        Schema::create('draw_group_month_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_group_id')->constrained('draw_groups')->cascadeOnDelete();
            $table->unsignedTinyInteger('cycle_month_no'); // 1-20
            $table->string('prize_name');
            $table->decimal('prize_value', 14, 2);
            $table->enum('metal_type', ['silver', 'gold']);
            $table->timestamps();

            $table->unique(['draw_group_id', 'cycle_month_no']);
        });

        Schema::create('draw_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_group_id')->constrained('draw_groups')->cascadeOnDelete();
            $table->unsignedTinyInteger('cycle_month_no');
            $table->timestamp('executed_at');
            $table->foreignId('winner_member_id')->constrained('members');
            $table->string('rng_proof')->nullable();
            $table->foreignId('upline_benefit_member_id')->nullable()->constrained('members');
            $table->enum('status', ['scheduled', 'executed', 'reconciled'])->default('scheduled');
            $table->timestamps();

            $table->unique(['draw_group_id', 'cycle_month_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draw_executions');
        Schema::dropIfExists('draw_group_month_configs');
        Schema::dropIfExists('draw_group_members');
        Schema::dropIfExists('draw_groups');
    }
};
