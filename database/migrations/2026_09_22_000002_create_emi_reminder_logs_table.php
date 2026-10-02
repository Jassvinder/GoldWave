<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-142 — one row per (installment, reminder kind) already sent, so `SendEmiReminders` can be re-run any number of
     * times without reminding twice. `kind` is `upcoming`, `due`, or `overdue_<n>` where n is the day of the overdue
     * milestone (1, 8, 15, …).
     */
    public function up(): void
    {
        Schema::create('emi_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_installment_id')->constrained('emi_installments')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['emi_installment_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emi_reminder_logs');
    }
};
