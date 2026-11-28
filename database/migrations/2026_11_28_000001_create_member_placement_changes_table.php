<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-252 (DOMAIN_LOGIC.md §3.5) — every Super Admin change of a not-yet-active member's sponsor or tree place, with the
 * old and new values, who made it and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_placement_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('old_sponsor_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('new_sponsor_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('old_parent_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('old_side', ['left', 'right'])->nullable();
            $table->foreignId('new_parent_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('new_side', ['left', 'right'])->nullable();
            $table->string('reason', 500);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();

            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_placement_changes');
    }
};
