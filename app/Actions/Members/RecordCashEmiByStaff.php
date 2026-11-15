<?php

namespace App\Actions\Members;

use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Jobs\ProcessCashPaymentApproval;
use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentModes;
use App\Support\PaymentInProgress;
use Illuminate\Validation\ValidationException;

/**
 * T-242 (DOMAIN_LOGIC.md §5 item 10) — Super Admin or the company Admin records a member's plan EMI as cash received
 * at the office (Member Detail → EMI card), for any member: one who never opened their account, or a blocked one who
 * can't log in (T-231). It is the member's earliest unpaid EMI, even one not due yet (advance, T-241), so several are
 * recorded one by one. It goes through the normal EMI payment path and is settled at once like an approved cash
 * payment (background, all or nothing); the earnings rules apply as usual (a blocked member's own earnings are skipped
 * by those rules).
 */
class RecordCashEmiByStaff
{
    public function __construct(private readonly InitiateEmiInstallmentPayment $initiate) {}

    /** The plan EMI that would be recorded now, or null when every EMI is paid. */
    public static function nextPayable(Member $member): ?EmiInstallment
    {
        $schedule = $member->emiSchedule;

        return $schedule ? InitiateEmiInstallmentPayment::nextPayable($schedule->id) : null;
    }

    public function __invoke(Member $member, User $operator): Payment
    {
        if (! $operator->isCompanyStaff()) {
            throw ValidationException::withMessages(['emi' => 'Only Super Admin or the company Admin can record a cash EMI.']);
        }

        if ($member->status !== 'active') {
            throw ValidationException::withMessages(['emi' => 'This member is not active yet.']);
        }

        $installment = self::nextPayable($member);

        if ($installment === null) {
            throw ValidationException::withMessages(['emi' => 'Every EMI of this member is already paid.']);
        }

        // The member's own pending Cash / GPay-UPI payment for this EMI is re-used, so approving it here settles it.
        $payment = ($this->initiate)($member, $installment, 'cash', notifyApprovers: false);

        if (! PaymentModes::needsApproval($payment->mode)) {
            throw ValidationException::withMessages(['emi' => 'An online payment for this EMI is in progress. Try again later.']);
        }

        if (! PaymentInProgress::claim($payment->id)) {
            throw ValidationException::withMessages(['emi' => 'This EMI payment is already being processed.']);
        }

        ProcessCashPaymentApproval::dispatch($payment, $operator);

        return $payment;
    }
}
