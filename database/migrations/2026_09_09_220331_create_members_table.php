<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §0/§4: Sponsor/Direct (`sponsor_id`) and Binary Position
     * (`placement_parent_id` + `placement_side`) are two independent
     * self-referencing relationships — never derive one from the other.
     *
     * `user_id` is nullable: a company dummy entry (§14) has no login identity
     * until a real leader is assigned to it (it converts into that leader's
     * member record in place — same row, `user_id` gets filled in then).
     *
     * `customer_id` is the GWL0N business identifier (§3.2) — unique, never
     * recycled, independent of the numeric primary key.
     *
     * No soft-deletes: members are never deleted, only status-transitioned
     * (see the Status Models in DOMAIN_LOGIC.md §18) — deleting a row here
     * would also corrupt the sponsor/placement chains of every descendant.
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('customer_id')->unique();

            $table->foreignId('sponsor_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('placement_parent_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('placement_side', ['left', 'right'])->nullable();

            $table->foreignId('membership_plan_id')->nullable()->constrained('membership_plans');
            $table->enum('status', ['draft', 'payment_pending', 'payment_confirmed', 'active', 'cancelled'])->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users');

            $table->foreignId('registered_via_store_id')->nullable()->constrained('stores');

            // Daily Dynamic Company Direct Entries (DOMAIN_LOGIC.md §14)
            $table->boolean('is_company_dummy')->default(false);
            $table->enum('dummy_status', ['generated', 'unassigned', 'assigned', 'disabled'])->nullable();
            $table->timestamp('dummy_generated_at')->nullable();
            $table->timestamp('dummy_assigned_at')->nullable();
            $table->foreignId('dummy_assigned_by')->nullable()->constrained('users');

            // Pending profile fields (DOMAIN_LOGIC.md §13) — one-time submission, then locked.
            $table->string('pan_card')->nullable();
            $table->string('aadhaar_card')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('passbook_or_cheque_path')->nullable();
            $table->text('address')->nullable();
            $table->timestamp('pending_fields_submitted_at')->nullable();

            // Cached balance for read performance — wallet_ledger_entries remains the audit source (DOMAIN_LOGIC.md §12).
            $table->decimal('wallet_balance', 14, 2)->default(0);
            $table->decimal('wallet_hold_amount', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['sponsor_id']);
            $table->index(['placement_parent_id', 'placement_side']);
            $table->index(['is_company_dummy', 'dummy_status']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
