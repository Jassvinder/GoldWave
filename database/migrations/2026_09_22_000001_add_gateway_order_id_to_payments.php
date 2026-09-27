<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-137 — the payment provider's order id (Razorpay `order_…`), created once per pending online payment. It is how a
     * webhook is mapped back to our `payments` row, and lets a retry reuse the same order instead of creating another.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_order_id')->nullable()->after('provider_reference')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway_order_id');
        });
    }
};
