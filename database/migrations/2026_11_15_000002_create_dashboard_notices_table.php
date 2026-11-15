<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-245 (DOMAIN_LOGIC.md §2.2b, INSTRUCTIONS.md M01) — offers, events and notices that Super Admin or the company Admin
 * show at the top of every member's dashboard, each within an optional date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_notices', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['offer', 'event', 'notice']);
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_notices');
    }
};
