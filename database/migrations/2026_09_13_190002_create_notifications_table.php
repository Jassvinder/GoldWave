<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §13 point 3 — the member must be notified via the
     * application's notification channel when a Change Request is reviewed.
     * Standard Laravel database-notification shape (`php artisan
     * notifications:table`) — the first task in this project to need
     * persisted notifications; `ARCHITECTURE.md`'s `Notifications/` folder
     * anticipated this (profile-request/cash-payment/payout status).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
