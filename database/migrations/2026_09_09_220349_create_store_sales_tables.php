<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §15/§16: `transaction_type` distinguishes New Sale from
     * Purchase/Repurchase — see the open question in DOMAIN_LOGIC.md §21 on
     * whether `store_profit_distributions` and `income_ledger_calculations`
     * (type=purchase_repurchase) ever apply to the same row here, or are
     * mutually exclusive by `transaction_type`. Do not assume either answer
     * in application code until the client confirms.
     *
     * Adds the deferred FK from `income_ledger_calculations.source_store_sale_id`
     * to this table now that both exist (see create_income_tables migration).
     */
    public function up(): void
    {
        Schema::create('store_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');
            // §16.8 — the tracked stock item this sale draws from, if any.
            $table->foreignId('store_inventory_item_id')->nullable()->constrained('store_inventory_items');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->enum('transaction_type', ['new_sale', 'purchase', 'repurchase']);
            $table->string('item_name');
            $table->enum('metal', ['gold', 'silver'])->nullable();
            $table->decimal('item_weight', 10, 3)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('rate', 14, 2)->nullable();
            // T-169 — server-side price snapshot; `metal_value` is the income base (null on typed-amount sales).
            $table->foreignId('metal_rate_id')->nullable()->constrained('metal_rates');
            $table->decimal('metal_value', 14, 2)->nullable();
            $table->decimal('making_charge_percent', 5, 2)->nullable();
            $table->decimal('making_charges', 14, 2)->nullable();
            // T-171 — added before GST when the bill is generated.
            $table->decimal('hallmark_charges', 14, 2)->nullable();
            $table->decimal('gst_percent', 5, 2)->nullable();
            $table->decimal('sale_amount', 14, 2);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->decimal('total_invoice_amount', 14, 2);
            $table->enum('payment_source', ['cash', 'store_wallet', 'other'])->default('cash');
            $table->foreignId('store_wallet_deduction_id')->nullable()->constrained('store_wallet_ledger_entries');
            $table->enum('distribution_status', ['pending', 'processed', 'reversed'])->default('pending');
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index(['store_id', 'transaction_type', 'status']);
        });

        // T-171 — the company's own plan-jewellery handover by Super Admin: a direct entry (no stock), priced like a
        // purchase, no income, billed at once.
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

        // T-171 — one row per hallmarked piece (HUID + its charge) on a store sale's or company delivery's bill.
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

        // A bill belongs to a store sale or a company delivery, and exists only once generated (T-171).
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_sale_id')->nullable()->unique()->constrained('store_sales')->cascadeOnDelete();
            $table->foreignId('company_delivery_id')->nullable()->unique()->constrained('company_deliveries');
            $table->string('invoice_no')->unique();
            $table->timestamp('generated_at');
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('store_profit_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_sale_id')->constrained('store_sales')->cascadeOnDelete();
            $table->enum('beneficiary_type', ['store_owner', 'sponsor_level_1', 'sponsor_level_2', 'sponsor_level_3']);
            $table->foreignId('beneficiary_member_id')->nullable()->constrained('members');
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users'); // for store_owner, if not also a member
            $table->decimal('rate_percent', 6, 3);
            $table->decimal('amount', 14, 2);
            $table->foreignId('rule_version_id')->constrained('rule_versions');
            $table->timestamps();

            $table->unique(['store_sale_id', 'beneficiary_type']);
        });

        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->foreign('source_store_sale_id')->references('id')->on('store_sales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->dropForeign(['source_store_sale_id']);
        });

        Schema::dropIfExists('store_profit_distributions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('hallmark_entries');
        Schema::dropIfExists('company_deliveries');
        Schema::dropIfExists('store_sales');
    }
};
