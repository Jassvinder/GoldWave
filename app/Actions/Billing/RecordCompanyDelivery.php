<?php

namespace App\Actions\Billing;

use App\Actions\Store\PriceStoreSale;
use App\Models\CompanyDelivery;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-171 (28-09-2026, user decisions — DOMAIN_LOGIC.md §16.2 T-171 note): the company hands a member's plan jewellery
 * over directly (Super Admin). It is a **direct entry** — the company's stock is not managed in the app — and it
 * triggers **no income** (no Store Profit, no Purchase/Repurchase). It is priced exactly like a purchase: the **locked
 * booking rate** for a Current Rate member, otherwise the delivery-day rate; making % from the delivery-day setting;
 * optional hallmarking (HUID + charge per piece) before GST; GST from the Super Admin setting. The bill is generated
 * at once (it is a genuine bill the member may take).
 */
class RecordCompanyDelivery
{
    public function __construct(
        private readonly ResolveDeliverableBenefit $resolveBenefit,
        private readonly PriceStoreSale $priceSale,
    ) {}

    /** @param list<array{huid: string, charge: float|int|string}> $hallmarks */
    public function __invoke(Member $member, string $itemName, float $weightPerPiece, int $quantity, User $superAdmin, array $hallmarks = []): CompanyDelivery
    {
        if (trim($itemName) === '' || $weightPerPiece <= 0 || $quantity < 1) {
            throw ValidationException::withMessages(['item_name' => 'Enter the item, its weight per piece and the quantity.']);
        }

        $hallmarkTotal = RecordHallmarks::validate($hallmarks, $quantity);

        return DB::transaction(function () use ($member, $itemName, $weightPerPiece, $quantity, $superAdmin, $hallmarks, $hallmarkTotal) {
            $benefit = ($this->resolveBenefit)($member);
            $benefit = $benefit->newQuery()->whereKey($benefit->id)->lockForUpdate()->firstOrFail();

            if ($benefit->delivered_at !== null) {
                throw ValidationException::withMessages(['customer_id' => "This member's plan jewellery has already been delivered."]);
            }

            $metal = $benefit->metal ?? $member->membershipPlan?->product_category;

            if ($metal !== 'gold' && $metal !== 'silver') {
                throw ValidationException::withMessages(['customer_id' => "This member's plan has no metal set."]);
            }

            $schedule = $member->emiSchedule()->first();
            $lockedRate = $schedule?->rate_booking_method === 'current_rate' && $schedule->rate_per_gram_at_booking !== null
                ? (float) $schedule->rate_per_gram_at_booking
                : null;

            $price = ($this->priceSale)($metal, $weightPerPiece, $quantity, $lockedRate, $schedule?->metal_rate_id, $hallmarkTotal);

            $delivery = CompanyDelivery::create([
                'product_benefit_id' => $benefit->id,
                'member_id' => $member->id,
                'item_name' => trim($itemName),
                'metal' => $metal,
                'item_weight' => $weightPerPiece,
                'quantity' => $quantity,
                'metal_rate_id' => $price['metal_rate_id'],
                'rate' => $price['rate_per_gram'],
                'metal_value' => $price['metal_value'],
                'making_charge_percent' => $price['making_charge_percent'],
                'making_charges' => $price['making_charges'],
                'hallmark_charges' => $price['hallmark_charges'],
                'sale_amount' => $price['subtotal'],
                'gst_percent' => $price['gst_percent'],
                'gst_amount' => $price['gst_amount'],
                'total_invoice_amount' => $price['total'],
                'delivered_by' => $superAdmin->id,
                'delivered_at' => now(),
            ]);

            RecordHallmarks::store($hallmarks, ['company_delivery_id' => $delivery->id]);

            $benefit->update(['delivered_at' => now(), 'store_id' => null]);

            Invoice::create([
                'company_delivery_id' => $delivery->id,
                'invoice_no' => 'GWD-'.now()->format('Ymd').'-'.str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT),
                'generated_at' => now(),
            ]);

            return $delivery->fresh(['invoice']) ?? $delivery;
        });
    }
}
