<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-185 (30-09-2026, DOMAIN_LOGIC.md §16.13) — Repurchase on EMI, Current Rate only. The whole schema for booking
 * (T-185a), break (T-185b) and delivery (T-185c) is created here at once:
 *
 * - `store_emi_bookings` — the Store Admin's request, Super Admin's decision, the held piece, and later the break
 *   (silver owed) and the delivery;
 * - `emi_schedules.kind` — `membership` (the plan's own schedule, every existing row) or `store_repurchase`; a store
 *   schedule has no membership plan, so `membership_plan_id` becomes nullable;
 * - `emi_installments.status` gains `cancelled` — the unpaid EMIs of a broken booking, never payable or overdue again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->enum('kind', ['membership', 'store_repurchase'])->default('membership')->after('member_id');
            $table->foreignId('membership_plan_id')->nullable()->change();
        });

        $this->installmentStatuses(['upcoming', 'due', 'paid', 'failed', 'overdue', 'cancelled']);

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
    }

    public function down(): void
    {
        Schema::dropIfExists('store_emi_bookings');

        $this->installmentStatuses(['upcoming', 'due', 'paid', 'failed', 'overdue']);

        Schema::table('emi_schedules', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }

    /**
     * Laravel stores an enum as a string with a CHECK constraint. PostgreSQL cannot `->change()` it, so the constraint is
     * replaced directly there; SQLite (the test database) rebuilds the column through `->change()`.
     *
     * @param  list<string>  $statuses
     */
    private function installmentStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $status) => "'{$status}'::character varying", $statuses));
            DB::statement('ALTER TABLE emi_installments DROP CONSTRAINT IF EXISTS emi_installments_status_check');
            DB::statement("ALTER TABLE emi_installments ADD CONSTRAINT emi_installments_status_check CHECK (status::text = ANY (ARRAY[{$list}]::text[]))");

            return;
        }

        Schema::table('emi_installments', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('upcoming')->change();
        });
    }
};
