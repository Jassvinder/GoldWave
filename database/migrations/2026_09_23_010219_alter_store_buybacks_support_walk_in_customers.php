<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §16.7 (revised 23-09-2026 — user decision): a Buyback
     * is primarily a **non-member** bringing back old jewellery — `member_id`
     * was originally a mandatory FK, the opposite of the real-world case.
     * `member_id` becomes nullable (a member seller is still linked when
     * one exists, e.g. a member buying back their own plan jewellery — see
     * §16.7's own text, "the member who owns it"); a non-member seller's
     * name/mobile are captured instead, for the same audit-log purpose
     * §16.3 already requires for a member (`RecordItemBuyback`/
     * `StoreActivityLogger` enforce "member OR walk-in name" at the
     * application layer, not a DB constraint — matching how
     * `RecordStoreSaleRequest` already handles item_name/store_inventory_item_id).
     */
    public function up(): void
    {
        Schema::table('store_buybacks', function (Blueprint $table) {
            $table->string('walk_in_name')->nullable()->after('member_id');
            $table->string('walk_in_mobile', 20)->nullable()->after('walk_in_name');
        });

        Schema::table('store_buybacks', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_buybacks', function (Blueprint $table) {
            $table->dropColumn(['walk_in_name', 'walk_in_mobile']);
        });

        Schema::table('store_buybacks', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable(false)->change();
        });
    }
};
