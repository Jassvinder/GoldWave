<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-196 (30-09-2026, user decision) — GPay/UPI to the company QR, proven by the Transaction/UTR Ref ID and a
 * screenshot, approved by Super Admin/Admin exactly like cash (`cash_status` is the shared manual-approval status).
 * Razorpay (`online`) stays in the enum, switched off by config.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->modes(['online', 'cash', 'wallet', 'upi']);

        Schema::table('payments', function (Blueprint $table) {
            $table->string('upi_reference', 64)->nullable()->unique()->after('mode');
            $table->string('upi_screenshot_path')->nullable()->after('upi_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['upi_reference']);
            $table->dropColumn(['upi_reference', 'upi_screenshot_path']);
        });

        $this->modes(['online', 'cash', 'wallet']);
    }

    /** @param  list<string>  $modes */
    private function modes(array $modes): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $mode) => "'{$mode}'::character varying", $modes));
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_mode_check');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_mode_check CHECK (mode::text = ANY (ARRAY[{$list}]::text[]))");

            return;
        }

        Schema::table('payments', function (Blueprint $table) use ($modes) {
            $table->enum('mode', $modes)->change();
        });
    }
};
