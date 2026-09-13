<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §3.2: Customer IDs (`GWL01`, `GWL10`, `GWL11`…) are
     * sequential and never recycled. A single locked-counter row
     * (`CustomerIdGenerator` reads it under `lockForUpdate()` inside the same
     * transaction as activation) gives atomic, race-free allocation without
     * a database-specific construct — a native Postgres `SEQUENCE` was
     * considered but rejected: `Docs/TEST.md`'s commands run the test suite
     * against SQLite (`phpunit.xml`), and Postgres-only DDL would silently
     * break `php artisan test` while working fine against the real `goldwave`
     * dev database, exactly the kind of gap this project can't afford.
     */
    public function up(): void
    {
        Schema::create('customer_id_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('next_value')->default(1);
        });

        DB::table('customer_id_counters')->insert(['next_value' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_id_counters');
    }
};
