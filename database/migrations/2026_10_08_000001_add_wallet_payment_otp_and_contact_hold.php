<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-215 (08-10-2026) — DOMAIN_LOGIC.md §12.2(d). A Member's wallet payment needs a one-time code (`otp_codes.purpose`
 * gains `wallet_payment`), and a changed email/mobile starts a 48-hour wallet-payment hold (`users.contact_changed_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->purposes(['login', 'password_reset', 'wallet_payment']);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('contact_changed_at')->nullable()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('contact_changed_at');
        });

        DB::table('otp_codes')->where('purpose', 'wallet_payment')->delete();

        $this->purposes(['login', 'password_reset']);
    }

    /** @param  list<string>  $purposes */
    private function purposes(array $purposes): void
    {
        // Same raw CHECK swap as `add_upi_payment_mode`: Postgres's enum()->change() emits invalid SQL.
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $purpose) => "'{$purpose}'::character varying", $purposes));
            DB::statement('ALTER TABLE otp_codes DROP CONSTRAINT IF EXISTS otp_codes_purpose_check');
            DB::statement("ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_purpose_check CHECK (purpose::text = ANY (ARRAY[{$list}]::text[]))");

            return;
        }

        Schema::table('otp_codes', function (Blueprint $table) use ($purposes) {
            $table->enum('purpose', $purposes)->change();
        });
    }
};
