<?php

namespace App\Actions\Store;

use App\Models\StoreEmiBooking;
use App\Services\RuleVersionService;

/**
 * DOMAIN_LOGIC.md §16.13 point 3 (T-185b) — run by the daily EMI job right after it marks EMIs overdue: every running
 * Repurchase on EMI with `store_emi_break_overdue_count` (default 3) overdue EMIs is broken (`BreakStoreEmiBooking`).
 * Idempotent: a broken booking is no longer `active`.
 *
 * @return list<StoreEmiBooking> the bookings broken by this run
 */
class BreakOverdueStoreEmiBookings
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly BreakStoreEmiBooking $break,
    ) {}

    /** @return list<StoreEmiBooking> */
    public function __invoke(): array
    {
        $threshold = max(1, (int) $this->rules->value('store_emi_break_overdue_count', 3));
        $broken = [];

        StoreEmiBooking::where('status', 'active')
            ->whereHas('emiSchedule.installments', fn ($query) => $query->where('status', 'overdue'), '>=', $threshold)
            ->orderBy('id')
            ->each(function (StoreEmiBooking $booking) use (&$broken) {
                $broken[] = ($this->break)($booking);
            });

        return $broken;
    }
}
