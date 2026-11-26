<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-248 (DOMAIN_LOGIC.md §8.8, INSTRUCTIONS.md S20) — draw winners typed in by Super Admin or the company Admin only to
 * show them on the member login banner, while the client's draw is held outside the system. Display only: no wallet,
 * payout, upline benefit or draw-engine effect. The winner's name and Customer ID always come from `members`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_winners', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('group_no');
            // The first day of the draw's month; only the month is shown.
            $table->date('draw_month');
            $table->foreignId('member_id')->constrained('members');
            $table->string('prize', 120);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['draw_month', 'member_id']);
            $table->index(['draw_month', 'group_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_winners');
    }
};
