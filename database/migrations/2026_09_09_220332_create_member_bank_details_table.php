<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §11.2: bank details are manually verified by Super Admin
     * before a payout can be processed against them; a cancelled cheque or
     * passbook image may serve as supporting proof.
     */
    public function up(): void
    {
        Schema::create('member_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('account_holder_name');
            $table->string('account_number');
            $table->string('ifsc_code');
            $table->string('bank_name');
            $table->string('proof_document_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_bank_details');
    }
};
