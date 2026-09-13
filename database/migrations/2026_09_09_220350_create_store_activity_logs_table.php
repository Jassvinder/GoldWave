<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.3: Super Admin needs a complete, filterable activity
     * log per store — who, what, when, affected member, reference, and
     * old/new values for data changes. `affected_reference` is polymorphic so
     * one log table can point at whichever record the action touched
     * (a sale, a wallet top-up, an invoice, a settings change, ...).
     */
    public function up(): void
    {
        Schema::create('store_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('operator_user_id')->constrained('users');
            $table->string('action_type');
            $table->foreignId('affected_member_id')->nullable()->constrained('members');
            $table->nullableMorphs('affected_reference');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'occurred_at']);
            $table->index(['store_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_activity_logs');
    }
};
