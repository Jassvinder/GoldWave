<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * T-117 (19-09-2026): sequential, never-recycled `GWLST0001…` Store IDs,
 * mirroring `CustomerIdGenerator`'s locked-counter pattern exactly.
 */
class StoreCodeGenerator
{
    public function next(): string
    {
        $row = DB::table('store_code_counters')->lockForUpdate()->first();

        DB::table('store_code_counters')->where('id', $row->id)->update([
            'next_value' => $row->next_value + 1,
        ]);

        return sprintf('GWLST%04d', $row->next_value);
    }
}
