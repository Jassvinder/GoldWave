<?php

namespace App\Actions\Profile;

use App\Models\MemberBankDetail;
use App\Models\User;
use App\Notifications\BankDetailsVerified;
use App\Services\Notifier;

/**
 * DOMAIN_LOGIC.md §11.2 point 8 — bank details are manually verified by
 * Super Admin before they can be used for a payout request
 * (`SubmitPayoutRequest` requires `verified_at`). Closes a precondition
 * T-009 assumed but never built (DOMAIN_LOGIC.md §21 T-012 pre-coding pass).
 * T-195 — the member is told, so they know payout requests are open.
 */
class VerifyMemberBankDetail
{
    public function __invoke(MemberBankDetail $bankDetail, User $operator): MemberBankDetail
    {
        $bankDetail->update([
            'verified_by' => $operator->id,
            'verified_at' => now(),
        ]);

        $verified = $bankDetail->fresh() ?? $bankDetail;

        if ($verified->member->user !== null) {
            Notifier::toUser($verified->member->user, new BankDetailsVerified($verified));
        }

        return $verified;
    }
}
