<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-018 pre-coding pass, DOMAIN_LOGIC.md §21: tracks the lifecycle of a
     * queued report export request (`INSTRUCTIONS.md` "Reports",
     * `PERFORMANCE_GUIDE.md`'s "large exports... must be queued, never
     * generated synchronously"). One row per request; the generated file
     * lives on the private `local` disk, referenced by `file_path`, never a
     * public URL.
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('report_type');
            $table->enum('format', ['csv', 'xlsx', 'pdf']);
            $table->json('filters')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
