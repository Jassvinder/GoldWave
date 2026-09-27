<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-117 (19-09-2026) — sequential, never-recycled `GWLST0001…` Store IDs,
     * used as the Admin/Store Login credential. Same locked-counter pattern
     * as `customer_id_counters` (`StoreCodeGenerator` reads it under
     * `lockForUpdate()`), for the same portability reason: no Postgres-only
     * `SEQUENCE`, since the test suite runs against SQLite.
     */
    public function up(): void
    {
        Schema::create('store_code_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('next_value')->default(1);
        });

        DB::table('store_code_counters')->insert(['next_value' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_code_counters');
    }
};
