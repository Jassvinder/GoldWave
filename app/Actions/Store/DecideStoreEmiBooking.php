<?php

namespace App\Actions\Store;

use App\Models\EmiInstallment;
use App\Models\EmiRateBookingEvent;
use App\Models\EmiSchedule;
use App\Models\StoreEmiBooking;
use App\Models\StoreInventoryItem;
use App\Models\User;
use App\Notifications\StoreEmiBookingDecided;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.13 points 1–2 (T-185a) — Super Admin (or the company Admin) decides a Repurchase on EMI request.
 *
 * - **Approve** prices the piece at the **approval day's** rate (`QuoteStoreEmiBooking`), holds it (stock − 1), and
 *   creates an ordinary Current Rate `emi_schedules` row of `kind = store_repurchase` with its 10 or 20 installments
 *   (EMI #1 due today, then monthly) and a `booked` event carrying the remaining value and pending count, so every EMI
 *   rule — payment, "Pay All Remaining EMIs", overdue hold, reminders — works on it unchanged.
 * - **Cancel** needs a message; nothing was held, so nothing is released.
 *
 * The member and the requesting Store Admin are told either way.
 */
class DecideStoreEmiBooking
{
    public function __construct(private readonly QuoteStoreEmiBooking $quote) {}

    public function approve(StoreEmiBooking $booking, User $approver): StoreEmiBooking
    {
        $decided = DB::transaction(function () use ($booking, $approver) {
            $locked = $this->lockPending($booking);
            $item = StoreInventoryItem::whereKey($locked->store_inventory_item_id)->lockForUpdate()->firstOrFail();

            if ($item->quantity < 1) {
                throw ValidationException::withMessages(['booking' => 'This piece is out of stock now, so the booking cannot be approved.']);
            }

            $quote = ($this->quote)($item, (int) $locked->installment_count);
            $amounts = $quote['installment_amounts'];
            $today = now()->startOfDay();

            $schedule = EmiSchedule::create([
                'member_id' => $locked->member_id,
                'kind' => 'store_repurchase',
                'membership_plan_id' => null,
                'total_installments' => $locked->installment_count,
                'rate_booking_method' => 'current_rate',
                'installment_amount' => $amounts[0],
                'metal_rate_id' => $quote['metal_rate_id'],
                'rate_per_gram_at_booking' => $quote['rate_per_gram'],
                'fixed_weight_grams' => $item->weight,
                'maintenance_cost' => $quote['maintenance_cost'],
                'rule_version_id' => $quote['rule_version_id'],
                'current_rate_booked_at' => now(),
                'installments_paid_at_booking' => 0,
                'amount_paid_at_booking' => 0,
            ]);

            foreach ($amounts as $i => $amount) {
                EmiInstallment::create([
                    'emi_schedule_id' => $schedule->id,
                    'installment_no' => $i + 1,
                    'due_date' => $today->copy()->addMonthsNoOverflow($i)->toDateString(),
                    'amount' => $amount,
                    'status' => $i === 0 ? 'due' : 'upcoming',
                ]);
            }

            EmiRateBookingEvent::create([
                'emi_schedule_id' => $schedule->id,
                'event' => 'booked',
                'performed_by_user_id' => $approver->id,
                'details' => [
                    'store_emi_booking_id' => $locked->id,
                    'rate_per_gram' => $quote['rate_per_gram'],
                    'fixed_weight_grams' => (float) $item->weight,
                    'metal_value' => $quote['metal_value'],
                    'making_charge_percent' => $quote['making_charge_percent'],
                    'making_charges' => $quote['making_charges'],
                    'total_value' => $quote['total_value'],
                    'paid_installments' => 0,
                    'paid_amount' => 0,
                    'remaining_value' => $quote['total_value'],
                    'pending_installments' => (int) $locked->installment_count,
                    'maintenance_cost' => $quote['maintenance_cost'],
                    'installment_amount' => $amounts[0],
                ],
            ]);

            $item->decrement('quantity');

            $locked->update([
                'status' => 'active',
                'emi_schedule_id' => $schedule->id,
                'decided_by' => $approver->id,
                'decided_at' => now(),
            ]);

            return $locked;
        });

        $this->notify($decided);

        return $decided;
    }

    public function cancel(StoreEmiBooking $booking, User $approver, string $message): StoreEmiBooking
    {
        if (trim($message) === '') {
            throw ValidationException::withMessages(['cancel_message' => 'Say why the request is cancelled.']);
        }

        $decided = DB::transaction(function () use ($booking, $approver, $message) {
            $locked = $this->lockPending($booking);

            $locked->update([
                'status' => 'cancelled',
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'cancel_message' => trim($message),
            ]);

            return $locked;
        });

        $this->notify($decided);

        return $decided;
    }

    private function lockPending(StoreEmiBooking $booking): StoreEmiBooking
    {
        $locked = StoreEmiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'pending') {
            throw ValidationException::withMessages(['booking' => 'This request was already decided.']);
        }

        return $locked;
    }

    private function notify(StoreEmiBooking $booking): void
    {
        $booking->load(['member.user', 'requestedBy']);

        if ($booking->member->user !== null) {
            Notifier::toUser($booking->member->user, new StoreEmiBookingDecided($booking, forMember: true));
        }

        Notifier::toUser($booking->requestedBy, new StoreEmiBookingDecided($booking, forMember: false));
    }
}
