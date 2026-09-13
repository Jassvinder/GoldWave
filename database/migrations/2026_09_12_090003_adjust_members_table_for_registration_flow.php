<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two fixes discovered while implementing T-003 (registration flow),
     * neither a business-rule change — see DOMAIN_LOGIC.md §21 discipline
     * (surfaced here, not silently worked around, since both touch schema):
     *
     * 1. `customer_id` was created NOT NULL in T-002, but DOMAIN_LOGIC.md
     *    §2.1/§3.1 is explicit that the Customer ID is generated only at
     *    activation (after confirmed payment) — a `draft`/`payment_pending`
     *    member has no Customer ID yet. Relaxed to nullable+unique (Postgres
     *    allows multiple NULLs under a unique index).
     * 2. `placement_parent_id` + `placement_side` had a plain index, not a
     *    unique constraint, even though DOMAIN_LOGIC.md §4.3 requires exactly
     *    one member per (parent, side) slot. Without a DB-level constraint, a
     *    race between two concurrent registrations resolving the same empty
     *    slot could place two members on the same slot. Added as a unique
     *    index so such a race fails loudly (and is retried in
     *    `RegisterMember`) instead of silently corrupting the tree.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->change();
        });

        // Postgres requires dropping+recreating the unique constraint separately from a column ->change().
        Schema::table('members', function (Blueprint $table) {
            $table->unique(['placement_parent_id', 'placement_side'], 'members_placement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique('members_placement_unique');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('customer_id')->nullable(false)->default('')->change();
        });
    }
};
