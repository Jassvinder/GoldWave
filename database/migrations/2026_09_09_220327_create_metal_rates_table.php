<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §5: Super Admin manages Gold and Silver rates together on one
     * Rate Settings page (INSTRUCTIONS.md S07), with effective-date history.
     */
    public function up(): void
    {
        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('metal', ['gold', 'silver']);
            // Entered per 10 gm and stored per gram (T-165).
            $table->decimal('rate_per_gram', 14, 2);
            // T-165 — the making % that applies with this effective-dated rate (one per metal).
            $table->decimal('making_charge_percent', 5, 2)->default(0);
            $table->date('effective_from');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['metal', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_rates');
    }
};
