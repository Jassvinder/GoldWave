<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §5/§10: EMI installment payments are payment *events*,
     * never new joinings. `payments.idempotency_key` and
     * `payments.provider_reference` are unique so a retried/duplicate gateway
     * callback can never create a second confirmed payment (§0, §19).
     *
     * `product_benefits.metal` is nullable — see the open Silver/Gold
     * entitlement item in DOMAIN_LOGIC.md §21; do not guess it when seeding.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('type', ['registration', 'emi_installment']);
            $table->decimal('amount', 14, 2);
            $table->enum('mode', ['online', 'cash']);
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');

            // Online payment verification (§10.1)
            $table->string('provider_reference')->nullable()->unique();
            $table->json('gateway_payload')->nullable();
            $table->string('idempotency_key')->nullable()->unique();

            // Cash payment approval workflow (§10.2)
            $table->enum('cash_status', ['pending_verification', 'approved', 'rejected'])->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type', 'status']);
        });

        Schema::create('emi_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans');
            $table->unsignedSmallInteger('total_installments');
            $table->timestamps();
        });

        Schema::create('emi_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_schedule_id')->constrained('emi_schedules')->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['upcoming', 'due', 'paid', 'failed', 'overdue'])->default('upcoming');
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->timestamps();

            $table->unique(['emi_schedule_id', 'installment_no']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('product_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans');
            $table->enum('metal', ['gold', 'silver'])->nullable();
            $table->foreignId('metal_rate_id')->nullable()->constrained('metal_rates');
            $table->decimal('rate_per_gram_at_entry', 14, 2)->nullable();
            $table->date('entry_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_benefits');
        Schema::dropIfExists('emi_installments');
        Schema::dropIfExists('emi_schedules');
        Schema::dropIfExists('payments');
    }
};
