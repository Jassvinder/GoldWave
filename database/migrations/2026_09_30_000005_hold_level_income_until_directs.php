<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-186 (30-09-2026, DOMAIN_LOGIC.md §6) — Level Income short of a level's qualified directs is no longer lapsed but
 * `held` (amount calculated at payment time, no wallet entry yet) and released the moment the directs are met.
 * `released_at` records when a held row became `paid`. Every existing T-179 `insufficient_directs` row (₹0, skipped)
 * becomes `held` with its real amount: rate × the source payment's amount, exactly what `CalculateLevelIncome` would
 * have paid. Releasing them is `php artisan level-income:release-held` (the same code the live triggers use).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setStatuses(['paid', 'skipped', 'held']);

        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable();
        });

        $rows = DB::table('income_ledger_calculations as i')
            ->join('payments as p', 'p.id', '=', 'i.source_payment_id')
            ->where('i.type', 'level_income')
            ->where('i.eligibility_status', 'skipped')
            ->where('i.skip_reason', 'insufficient_directs')
            ->get(['i.id', 'i.rate_percent', 'p.amount as payment_amount']);

        foreach ($rows as $row) {
            DB::table('income_ledger_calculations')->where('id', $row->id)->update([
                'eligibility_status' => 'held',
                'amount' => round(((float) $row->payment_amount) * ((float) $row->rate_percent) / 100, 2),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('income_ledger_calculations')->where('eligibility_status', 'held')->update(['eligibility_status' => 'skipped', 'amount' => 0]);

        Schema::table('income_ledger_calculations', function (Blueprint $table) {
            $table->dropColumn('released_at');
        });

        $this->setStatuses(['paid', 'skipped']);
    }

    /** @param list<string> $statuses */
    private function setStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $s) => "'{$s}'", $statuses));
            DB::statement('ALTER TABLE income_ledger_calculations DROP CONSTRAINT IF EXISTS income_ledger_calculations_eligibility_status_check');
            DB::statement("ALTER TABLE income_ledger_calculations ADD CONSTRAINT income_ledger_calculations_eligibility_status_check CHECK (eligibility_status IN ({$list}))");

            return;
        }

        Schema::table('income_ledger_calculations', function (Blueprint $table) use ($statuses) {
            $table->enum('eligibility_status', $statuses)->default('paid')->change();
        });
    }
};
