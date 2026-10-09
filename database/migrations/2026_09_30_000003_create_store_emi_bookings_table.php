<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-185 (30-09-2026, DOMAIN_LOGIC.md §16.13) — Repurchase on EMI, Current Rate only: the Store Admin's request, Super
 * Admin's decision, the held piece, and later the break (silver owed) and the delivery. `emi_schedules.kind` and
 * `emi_installments.status = cancelled` live in the create_emi_and_payments migration.
 *
 * Adds the deferred FK from `store_sales.store_emi_booking_id` to this table now that both exist (the same pattern as
 * create_store_sales_tables uses for `income_ledger_calculations.source_store_sale_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_emi_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('store_inventory_item_id')->constrained('store_inventory_items');
            $table->foreignId('requested_by')->constrained('users');
            // Snapshot of the piece at request time (the inventory row may later change).
            $table->string('item_name');
            $table->enum('metal', ['gold', 'silver']);
            $table->decimal('weight_grams', 10, 3);
            $table->unsignedTinyInteger('installment_count'); // 10 or 20
            $table->enum('status', ['pending', 'cancelled', 'active', 'completed', 'broken', 'delivered'])->default('pending');
            // What the Store Admin saw when asking; the real figures are fixed on the approval day.
            $table->json('estimate')->nullable();

            // Decision (T-185a).
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('cancel_message')->nullable();
            $table->foreignId('emi_schedule_id')->nullable()->constrained('emi_schedules');

            // Break (T-185b): silver owed for the principal paid, at the rate in force on the last paid EMI's date.
            $table->timestamp('broken_at')->nullable();
            $table->decimal('principal_paid', 14, 2)->nullable();
            $table->foreignId('silver_metal_rate_id')->nullable()->constrained('metal_rates');
            $table->decimal('silver_rate_per_gram', 14, 2)->nullable();
            $table->decimal('silver_grams_owed', 10, 3)->nullable();

            // Delivery (T-185c).
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['status', 'id']);
        });

        Schema::table('store_sales', function (Blueprint $table) {
            $table->foreign('store_emi_booking_id')->references('id')->on('store_emi_bookings');
        });
    }

    public function down(): void
    {
        Schema::table('store_sales', function (Blueprint $table) {
            $table->dropForeign(['store_emi_booking_id']);
        });

        Schema::dropIfExists('store_emi_bookings');
    }
};
