<?php

namespace App\Actions\Members;

use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Jobs\ProcessCashPaymentApproval;
use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentInProgress;
use Illuminate\Validation\ValidationException;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — a blocked member can't log in to start a cash EMI, so Super Admin records the next
 * due plan EMI as cash received (Member Detail → EMI card). It is created through the normal EMI payment path and
 * settled like an approved cash payment (background, all or nothing). The EMI and the product count as usual; the
 * blocked member's own earnings are skipped by the earnings rules themselves.
 */
class RecordCashEmiForBlockedMember
{
    public function __construct(private readonly InitiateEmiInstallmentPayment $initiate) {}

    /** The plan EMI that would be recorded now, or null when nothing is due. */
    public static function nextDue(Member $member): ?EmiInstallment
    {
        $schedule = $member->emiSchedule;

        return $schedule?->installments()->whereIn('status', ['due', 'overdue'])->orderBy('installment_no')->first();
    }

    public function __invoke(Member $member, User $operator): Payment
    {
        if (! $member->isBlocked()) {
            throw ValidationException::withMessages(['emi' => 'Record Cash EMI is only for a blocked member; others pay from their own EMI page.']);
        }

        $installment = self::nextDue($member);

        if ($installment === null) {
            throw ValidationException::withMessages(['emi' => 'No EMI of this member is due right now.']);
        }

        $payment = ($this->initiate)($member, $installment, 'cash');

        if (! PaymentInProgress::claim($payment->id)) {
            throw ValidationException::withMessages(['emi' => 'This EMI payment is already being processed.']);
        }

        ProcessCashPaymentApproval::dispatch($payment, $operator);

        return $payment;
    }
}
