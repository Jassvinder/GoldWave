<?php

namespace App\Actions\Store;

use App\Actions\Registration\CalculateEmiRateBooking;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.13 (T-185) — what a Repurchase on EMI of one piece costs at **today's** rate: the piece's weight ×
 * rate + making, over 10 or 20 EMIs with the plan's declining maintenance (T-167) — `CalculateEmiRateBooking`'s own
 * Current Rate formula, never a second copy of it.
 */
class QuoteStoreEmiBooking
{
    public function __construct(private readonly CalculateEmiRateBooking $calculate) {}

    /** @return array{metal_rate_id: int, rate_per_gram: float, metal_value: float, making_charge_percent: float, making_charges: float, total_value: float, installment_amounts: non-empty-list<float>, maintenance_cost: float, total_maintenance: float, rule_version_id: int|null} */
    public function __invoke(StoreInventoryItem $item, int $installmentCount): array
    {
        if (! in_array($installmentCount, StoreEmiBooking::INSTALLMENT_COUNTS, true)) {
            throw ValidationException::withMessages(['installment_count' => 'Choose 10 or 20 EMIs.']);
        }

        $booking = $this->calculate->currentRateFor((string) $item->metal, (float) $item->weight, $installmentCount);

        return [
            'metal_rate_id' => (int) $booking['metal_rate_id'],
            'rate_per_gram' => (float) $booking['rate_per_gram'],
            'metal_value' => (float) $booking['metal_value'],
            'making_charge_percent' => (float) $booking['making_charge_percent'],
            'making_charges' => (float) $booking['making_charges'],
            'total_value' => (float) $booking['total_value'],
            'installment_amounts' => $booking['installment_amounts'] ?? [(float) $booking['installment_amount']],
            'maintenance_cost' => (float) $booking['maintenance_cost'],
            'total_maintenance' => (float) $booking['total_maintenance'],
            'rule_version_id' => $booking['rule_version_id'],
        ];
    }
}
