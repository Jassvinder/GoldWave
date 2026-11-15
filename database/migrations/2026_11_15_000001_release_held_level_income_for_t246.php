<?php

use App\Actions\Compensation\ReleaseHeldLevelIncome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * T-246 (DOMAIN_LOGIC.md §6, user decision 15-11-2026) — Level Income now counts every active direct with a paid
 * registration, without the Pair Qualification EMIs. Income already held under the old count is released once, on
 * the deploy that brings the new rule, for every member who now meets their level's directs. Same idempotent action
 * as `php artisan level-income:release-held`; on a fresh database there is nothing held, so it does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $released = app(ReleaseHeldLevelIncome::class)->forAll();

        Log::info('T-246: held Level Income released on deploy', [
            'members' => count($released),
            'total' => round(array_sum($released), 2),
            'by_customer_id' => $released,
        ]);
    }

    public function down(): void
    {
        // Wallet credits are never reversed by a rollback; a mistaken credit is corrected through the ledger.
    }
};
