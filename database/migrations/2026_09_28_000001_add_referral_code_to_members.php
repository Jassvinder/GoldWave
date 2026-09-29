<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-162 (28-09-2026) — a member's permanent registration link
 * (`/join?ref={referral_code}`). Random and non-sequential, so the link
 * never exposes a Customer ID or lets anyone guess other members' links.
 * Filled lazily the first time a member's link is shown, then never changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
