<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §3.2: sequential, never-recycled `GWL0N…` Customer IDs.
 * Backed by a single locked counter row (`customer_id_counters`) read under
 * `lockForUpdate()` — always called from inside
 * ActivateMembershipOnPaymentConfirmed's transaction, so this join's that
 * transaction rather than opening a second one, and the lock is held until
 * activation commits.
 */
class CustomerIdGenerator
{
    public function next(): string
    {
        $row = DB::table('customer_id_counters')->lockForUpdate()->first();

        DB::table('customer_id_counters')->where('id', $row->id)->update([
            'next_value' => $row->next_value + 1,
        ]);

        return sprintf('GWL%02d', $row->next_value);
    }
}
