<?php

namespace App\Actions\Payments;

use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Services\Notifier;
use App\Services\Payments\PaymentModes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251 part 3) — "Pay for all my IDs": the earliest unpaid plan EMI of each of the person's
 * active IDs on an EMI plan, in one combined Cash / GPay-UPI payment (`payment_batches`, type `emi_installment`).
 * Each ID keeps its own `payments` row, so approval settles every EMI exactly as if paid alone and each ID's income
 * runs on its own (`ApprovePaymentBatch`). An ID whose next EMI already has a payment waiting is left out.
 */
class InitiateCombinedEmiPayment
{
    /** Fewer than this many EMIs is just a normal EMI payment. */
    public const MIN_IDS = 2;

    /**
     * The EMIs one "Pay for all my IDs" would cover now, first ID first.
     *
     * @return list<array{member: Member, installment: EmiInstallment}>
     */
    public static function items(User $user, bool $lock = false): array
    {
        $members = Member::with('emiSchedule.membershipPlan')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $items = [];

        foreach ($members as $member) {
            $schedule = $member->emiSchedule;

            if ($schedule === null || ! $schedule->membershipPlan?->isEmiPlan()) {
                continue;
            }

            $installment = InitiateEmiInstallmentPayment::nextPayable($schedule->id, $lock);

            if ($installment === null) {
                continue;
            }

            $existing = $installment->payment_id !== null ? Payment::find($installment->payment_id) : null;

            if ($existing?->status === 'pending') {
                continue;
            }

            $items[] = ['member' => $member, 'installment' => $installment];
        }

        return $items;
    }

    public function __invoke(User $user, string $mode): PaymentBatch
    {
        if (! PaymentModes::needsApproval($mode)) {
            throw ValidationException::withMessages(['mode' => 'EMIs of several IDs are paid together by Cash or GPay/UPI only.']);
        }

        $batch = DB::transaction(function () use ($user, $mode): PaymentBatch {
            // One request at a time per person, so the same EMI never lands in two payments.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $items = self::items($user, lock: true);

            if (count($items) < self::MIN_IDS) {
                throw ValidationException::withMessages(['combined_emi' => 'Fewer than 2 of your IDs have an EMI to pay right now. Pay it from that ID\'s EMI page.']);
            }

            $batch = PaymentBatch::create([
                'type' => 'emi_installment',
                'mode' => $mode,
                'amount' => 0,
                'status' => 'pending',
                'cash_status' => PaymentModes::initialApprovalStatus($mode),
                'created_by' => $user->id,
            ]);

            foreach ($items as ['member' => $member, 'installment' => $installment]) {
                $payment = Payment::create([
                    'member_id' => $member->id,
                    'payment_batch_id' => $batch->id,
                    'type' => 'emi_installment',
                    'amount' => $installment->amount,
                    'mode' => $mode,
                    'status' => 'pending',
                    'idempotency_key' => (string) Str::uuid(),
                    'cash_status' => PaymentModes::initialApprovalStatus($mode),
                ]);

                $installment->update(['payment_id' => $payment->id]);
            }

            $batch->update(['amount' => $batch->payments()->sum('amount')]);

            return $batch;
        });

        $first = $batch->payments()->orderBy('id')->firstOrFail();
        Notifier::toSuperAdmins(new CashPaymentAwaitingApproval($first));

        return $batch;
    }
}
