<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §2.1/§2.2: mobile is now a mandatory registration field
     * alongside email, and both are valid login identifiers. Nullable at the
     * DB level (the starter-kit `users` table pre-dates this requirement and
     * a super-admin/dev seed user may have no mobile) — registration-time
     * validation enforces it as required for member accounts.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable()->unique()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile');
        });
    }
};
