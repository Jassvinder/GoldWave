<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-251 (DOMAIN_LOGIC.md §3.4, DATABASE_SCHEMA.md "T-251 additions") — one person (user) may hold several member IDs,
 * and several IDs can be paid with one combined payment. Forward-only in practice: no existing row changes, every
 * current person keeps exactly one ID and every current payment has no batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->index('user_id');
        });

        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['registration', 'emi_installment']);
            $table->enum('mode', ['cash', 'upi']);
            $table->decimal('amount', 14, 2);
            $table->string('upi_reference', 64)->nullable()->unique();
            $table->string('upi_screenshot_path')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('cash_status', ['pending_verification', 'approved', 'rejected'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'cash_status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_batch_id')->nullable()->after('member_id')->constrained('payment_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_batch_id');
        });

        Schema::dropIfExists('payment_batches');

        // Restoring the unique index fails once a person holds two IDs, which is the intended guard.
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
