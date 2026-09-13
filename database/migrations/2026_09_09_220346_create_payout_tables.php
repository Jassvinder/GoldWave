<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §11: a member can only submit a payout *request* (no
     * self-withdrawal); `payout_transactions` is the actual Super
     * Admin-processed payment against an approved request, with an immutable
     * amount/beneficiary snapshot (§11.2) so a later bank-detail edit never
     * changes what a historical payout record says was paid.
     */
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('requested_amount', 14, 2);
            $table->foreignId('member_bank_detail_id')->nullable()->constrained('member_bank_details');
            $table->enum('status', ['pending', 'approved', 'processing', 'processed', 'failed', 'cancelled', 'rejected'])->default('pending');
            $table->foreignId('hold_ledger_entry_id')->nullable()->constrained('wallet_ledger_entries');
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });

        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained('payout_requests')->cascadeOnDelete();
            $table->decimal('amount_snapshot', 14, 2);
            $table->json('beneficiary_snapshot');
            $table->enum('method', ['cheque', 'gpay_upi', 'bank_transfer', 'in_app_provider']);
            $table->string('reference')->nullable();
            $table->string('batch_reference')->nullable();
            $table->decimal('tds_amount', 14, 2)->default(0);
            $table->decimal('processing_fee', 14, 2)->default(0);
            $table->enum('status', ['processed', 'failed'])->default('processed');
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index('batch_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_transactions');
        Schema::dropIfExists('payout_requests');
    }
};
