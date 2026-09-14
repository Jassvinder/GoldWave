<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §21 T-012 pre-coding pass: this column duplicated
     * `member_bank_details.proof_document_path` (T-009) — both described the
     * same passbook/cancelled-cheque proof image. One source of truth now.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('passbook_or_cheque_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('passbook_or_cheque_path')->nullable();
        });
    }
};
