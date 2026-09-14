<?php

namespace App\Actions\Profile;

use App\Models\MemberBankDetail;
use App\Models\User;

/**
 * DOMAIN_LOGIC.md §11.2 point 8 — bank details are manually verified by
 * Super Admin before they can be used for a payout request
 * (`SubmitPayoutRequest` requires `verified_at`). Closes a precondition
 * T-009 assumed but never built (DOMAIN_LOGIC.md §21 T-012 pre-coding pass).
 */
class VerifyMemberBankDetail
{
    public function __invoke(MemberBankDetail $bankDetail, User $operator): MemberBankDetail
    {
        $bankDetail->update([
            'verified_by' => $operator->id,
            'verified_at' => now(),
        ]);

        return $bankDetail->fresh();
    }
}
