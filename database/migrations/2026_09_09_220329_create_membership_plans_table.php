<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §3: the 5 plans (A-E). `product_category` is intentionally
     * nullable — see DOMAIN_LOGIC.md §21 "Still open": the Silver/Gold entitlement
     * per plan is CLIENT CONFIRMATION REQUIRED. Do not seed this column with a
     * guessed value.
     *
     * Payments/EMI installments store their own amount independently of this
     * table (DOMAIN_LOGIC.md §22 "store calculation snapshots") so a later change
     * to a plan's amount here never retroactively alters historical transactions.
     */
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique(); // A, B, C, D, E
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->unsignedSmallInteger('installment_count')->nullable(); // null = one-time plan
            $table->enum('product_category', ['silver', 'gold'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
