<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit log of every Future→Current Rate booking and every Super Admin revert of one (DOMAIN_LOGIC.md
     * §3.0). Rows are never updated or deleted. `reason` is required by the revert flow, null for a booking.
     */
    public function up(): void
    {
        Schema::create('emi_rate_booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_schedule_id')->constrained('emi_schedules')->cascadeOnDelete();
            $table->enum('event', ['booked', 'reverted']);
            $table->foreignId('performed_by_user_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['emi_schedule_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emi_rate_booking_events');
    }
};
