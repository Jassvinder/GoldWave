<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §12.2(a) — T-151, Store-Wallet-Funded Cash Collection:
     * when a member pays a registration or EMI installment in cash directly
     * to a store, the store settles it instantly from its own Store Wallet
     * (mirroring `ConfirmStoreSale`'s existing `payment_source=store_wallet`
     * for `store_sales`) instead of Super Admin manually approving it.
     * `mode` stays `cash` (that is genuinely what the member did) —
     * `paying_store_id` records which store's wallet absorbed the
     * settlement, nullable because most cash payments still go through
     * Super Admin with no store involved. §16.12 (T-152) reads this column
     * to decide whether a store-delivered joining needs restocking.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('paying_store_id')->nullable()->after('member_id')->constrained('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paying_store_id');
        });
    }
};
