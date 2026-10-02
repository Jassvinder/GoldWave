<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-166 (28-09-2026, user decision — DOMAIN_LOGIC.md §3.0 note): a member's
 * "Book at Current Rate" is now a request that Super Admin approves or
 * cancels. The booking itself (rate, EMIs) is only applied on approval, at the
 * approval day's rate and pending EMIs, and is logged in
 * `emi_rate_booking_events` as before. `estimate` is the member's view at
 * request time, for reference only. A cancel carries a message shown back to
 * the member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('current_rate_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_schedule_id')->constrained('emi_schedules')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
            $table->json('estimate')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('cancel_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['member_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('current_rate_booking_requests');
    }
};
