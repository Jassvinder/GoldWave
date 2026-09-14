<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §21 T-013 pre-coding pass: `is_company_root` marks the
     * single seeded "Company" Member that anchors every daily dummy batch's
     * Right-side placement (and every dummy's `sponsor_id`) — no such anchor
     * existed anywhere in the schema before. `placeholder_name` gives a
     * generated dummy record somewhere to hold its "placeholder/dummy name
     * data" (DOMAIN_LOGIC.md §14.2 point 3) — `members` never had a name
     * column at all (real names live only on `users`, which a dummy has none
     * of until leader assignment).
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('is_company_root')->default(false)->after('dummy_assigned_by');
            $table->string('placeholder_name')->nullable()->after('is_company_root');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['is_company_root', 'placeholder_name']);
        });
    }
};
