<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-171 (28-09-2026, user decisions — DOMAIN_LOGIC.md §16.2 T-171 note):
 *
 * - `company_deliveries` — the company's own plan-jewellery handover by Super Admin: a direct entry (no stock),
 *   priced like a purchase, triggering no income, with its own bill.
 * - `hallmark_entries` — one row per hallmarked piece (HUID number + its hallmark charge), for a store sale's or a
 *   company delivery's bill, so every HUID stays on record.
 * - `store_sales.hallmark_charges` — the sale's hallmark total, added before GST when its bill is generated.
 * - `invoices` — a bill now belongs to a store sale **or** a company delivery, and is created only when someone
 *   generates it ("Generate bill"); later copies are duplicates of the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_benefit_id')->unique()->constrained('product_benefits');
            $table->foreignId('member_id')->constrained('members');
            $table->string('item_name');
            $table->enum('metal', ['gold', 'silver']);
            $table->decimal('item_weight', 10, 3);
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('metal_rate_id')->nullable()->constrained('metal_rates');
            $table->decimal('rate', 14, 2);
            $table->decimal('metal_value', 14, 2);
            $table->decimal('making_charge_percent', 5, 2);
            $table->decimal('making_charges', 14, 2);
            $table->decimal('hallmark_charges', 14, 2)->default(0);
            $table->decimal('sale_amount', 14, 2);
            $table->decimal('gst_percent', 5, 2);
            $table->decimal('gst_amount', 14, 2);
            $table->decimal('total_invoice_amount', 14, 2);
            $table->foreignId('delivered_by')->constrained('users');
            $table->timestamp('delivered_at');
            $table->timestamps();
        });

        Schema::table('store_sales', function (Blueprint $table) {
            $table->decimal('hallmark_charges', 14, 2)->nullable()->after('making_charges');
        });

        Schema::create('hallmark_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_sale_id')->nullable()->constrained('store_sales')->cascadeOnDelete();
            $table->foreignId('company_delivery_id')->nullable()->constrained('company_deliveries')->cascadeOnDelete();
            $table->unsignedInteger('piece_no');
            $table->string('huid', 16);
            $table->decimal('charge', 14, 2);
            $table->timestamps();

            $table->index('huid');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('store_sale_id')->nullable()->change();
            $table->foreignId('company_delivery_id')->nullable()->unique()->after('store_sale_id')->constrained('company_deliveries');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_delivery_id');
        });

        Schema::dropIfExists('hallmark_entries');

        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropColumn('hallmark_charges');
        });

        Schema::dropIfExists('company_deliveries');
    }
};
