<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §7.4/§18.2/§22: every configurable business value (level
     * income rates, pair milestones, booster levels, pair-qualification EMI
     * counts, store distribution rates, purchase/repurchase rates, payout
     * minimum/TDS/fee, GST/TDS) must be Super-Admin-configurable, never
     * hard-coded, and versioned so a historical calculation stays reproducible
     * even after settings change later.
     *
     * Rather than one normalized table per config type (8+ near-identical
     * tables), this uses a versioned key/value store: `rule_versions` is the
     * publishable version header, `rule_values` holds one JSON blob per config
     * key against that version (key examples: level_income_rates,
     * pair_milestones, pair_qualification_emis, booster_levels,
     * store_distribution_rates, purchase_repurchase_rates, payout_settings,
     * tax_settings). Every finalized calculation elsewhere stores the
     * `rule_version_id` it used, never a live re-lookup of "current" settings.
     */
    public function up(): void
    {
        Schema::create('rule_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_no')->unique();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rule_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('key');
            $table->json('value');
            $table->timestamps();

            $table->unique(['rule_version_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_values');
        Schema::dropIfExists('rule_versions');
    }
};
