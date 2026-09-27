<?php

namespace App\Support;

use App\Http\Controllers\Registration\RegistrationController;
use App\Models\Payment;

/**
 * Where a member lands once an online payment attempt is over (paid or not). Registration payments happen before
 * login (DOMAIN_LOGIC.md §2.2), so their status page needs the signed public URL; EMI-installment payments happen from
 * an authenticated member session, so the plain EMI route is enough.
 */
class PaymentReturnUrl
{
    public static function for(Payment $payment): string
    {
        if ($payment->type === 'registration') {
            return RegistrationController::signedStatusUrl($payment->member()->firstOrFail());
        }

        return route('member.emi.index');
    }
}
