<?php

namespace App\Actions\Store;

use App\Models\EmiInstallment;
use App\Models\EmiRateBookingEvent;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Notifications\StoreEmiBookingBroken;
use App\Services\Notifier;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §16.13 point 3 (T-185b) — a running Repurchase on EMI breaks: its unpaid EMIs are `cancelled` (never
 * payable, never overdue again), the held piece goes back to the store's stock, and the member is owed **silver** for
 * the principal paid (what was paid minus maintenance), at the silver rate in force on the date the last EMI was paid.
 * With the overdue EMIs gone the member's held earnings are released (T-182). Level Income already paid stays.
 *
 * Principal paid = the booking's principal (`remaining value ÷ EMIs`, from its `booked` event) × EMIs paid — every
 * paid store EMI carried exactly one principal (the T-167 split), so this is the amount paid minus its maintenance.
 */
class BreakStoreEmiBooking
{
    public function __construct(private readonly WalletLedgerService $wallet) {}

    public function __invoke(StoreEmiBooking $booking): StoreEmiBooking
    {
        $broken = DB::transaction(function () use ($booking) {
            $locked = StoreEmiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active' || $locked->emi_schedule_id === null) {
                return null;
            }

            $installments = EmiInstallment::where('emi_schedule_id', $locked->emi_schedule_id)->lockForUpdate()->get();
            $paid = $installments->where('status', 'paid');

            $booked = EmiRateBookingEvent::where('emi_schedule_id', $locked->emi_schedule_id)->where('event', 'booked')->latest('id')->first();
            $principal = round((float) ($booked?->details['remaining_value'] ?? 0) / max(1, (int) $locked->installment_count), 2);
            $principalPaid = round($principal * $paid->count(), 2);

            $lastPaidAt = Payment::whereIn('id', $paid->pluck('payment_id')->filter())->max('paid_at');
            $silverRate = $lastPaidAt === null ? null : MetalRate::where('metal', 'silver')
                ->whereDate('effective_from', '<=', substr((string) $lastPaidAt, 0, 10))
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            $grams = $silverRate !== null && $principalPaid > 0
                ? round($principalPaid / (float) $silverRate->rate_per_gram, 3)
                : 0.0;

            EmiInstallment::where('emi_schedule_id', $locked->emi_schedule_id)
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->update(['status' => 'cancelled']);

            StoreInventoryItem::whereKey($locked->store_inventory_item_id)->increment('quantity');

            $locked->update([
                'status' => 'broken',
                'broken_at' => now(),
                'principal_paid' => $principalPaid,
                'silver_metal_rate_id' => $silverRate?->id,
                'silver_rate_per_gram' => $silverRate?->rate_per_gram,
                'silver_grams_owed' => $grams,
            ]);

            return $locked;
        });

        if ($broken === null) {
            return $booking->fresh() ?? $booking;
        }

        // The cancelled EMIs no longer count as overdue, so anything held because of them is released.
        $this->wallet->releaseHeldEarnings($broken->member()->firstOrFail());

        $broken->load(['member.user', 'requestedBy', 'store']);
        Notifier::toUser($broken->member->user, new StoreEmiBookingBroken($broken, forMember: true));
        Notifier::toUser($broken->requestedBy, new StoreEmiBookingBroken($broken, forMember: false));

        return $broken;
    }
}
