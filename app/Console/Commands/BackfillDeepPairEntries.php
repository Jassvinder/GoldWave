<?php

namespace App\Console\Commands;

use App\Models\PairEntry;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair (01-10-2026). `BinaryPlacementResolver::ancestorsWithSide()` used to stop at 500 levels, so a joining
 * deeper than that gave no Pair entry to the uplines above the 500th (DOMAIN_LOGIC.md §7: the Beneficiary Chain is
 * unbounded). Only a joining that already has 500 or more entries can have been cut off, so only those are rechecked.
 * Each missing upline gets the same `unused` entry `CreatePairEntries` would have made: same side, metal and source
 * payment, skipping an unassigned dummy exactly as `CreatePairEntries` does. The tree is read once into memory. Idempotent.
 */
class BackfillDeepPairEntries extends Command
{
    use ConfirmableTrait;

    protected $signature = 'pair-entries:backfill-deep {--force : run in production without asking}';

    protected $description = 'Create the Pair entries a joining more than 500 levels deep missed under the old depth cap.';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $members = DB::table('members')->get(['id', 'customer_id', 'placement_parent_id', 'placement_side', 'is_company_dummy', 'dummy_status'])->keyBy('id');

        $payments = DB::table('pair_entries')
            ->join('payments', 'payments.id', '=', 'pair_entries.source_payment_id')
            ->groupBy('pair_entries.source_payment_id', 'payments.member_id')
            ->havingRaw('count(*) >= 500')
            ->get(['pair_entries.source_payment_id', 'payments.member_id']);

        $created = [];

        foreach ($payments as $payment) {
            $existing = DB::table('pair_entries')->where('source_payment_id', $payment->source_payment_id)->pluck('member_id')->flip();
            $metal = DB::table('pair_entries')->where('source_payment_id', $payment->source_payment_id)->value('metal');
            $rows = [];
            $current = $members->get($payment->member_id);
            $visited = [];

            while ($current !== null && $current->placement_parent_id !== null && ! isset($visited[$current->placement_parent_id])) {
                $parent = $members->get($current->placement_parent_id);
                $visited[$current->placement_parent_id] = true;

                if ($parent === null) {
                    break;
                }

                $isUnassignedDummy = $parent->is_company_dummy && $parent->dummy_status !== 'assigned';

                if (! $isUnassignedDummy && ! $existing->has($parent->id)) {
                    $rows[] = [
                        'member_id' => $parent->id,
                        'side' => $current->placement_side,
                        'metal' => $metal,
                        'source_payment_id' => $payment->source_payment_id,
                        'status' => 'unused',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $created[$parent->customer_id][$current->placement_side] = ($created[$parent->customer_id][$current->placement_side] ?? 0) + 1;
                }

                $current = $parent;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                PairEntry::insert($chunk);
            }
        }

        $this->info('Rechecked '.$payments->count().' joining(s); created '.array_sum(array_map('array_sum', $created)).' missing Pair entries.');

        foreach ($created as $customerId => $sides) {
            $this->line(sprintf('  %s  L%d / R%d', $customerId, $sides['left'] ?? 0, $sides['right'] ?? 0));
        }

        return self::SUCCESS;
    }
}
