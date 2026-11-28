<?php

namespace App\Actions\Profile;

use App\Models\Member;
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

        // T-251 (DOMAIN_LOGIC.md §3.4) — the same bank account on the person's other IDs (copied from one form) is the
        // same account, so it is verified with it: the Super Admin checks the passbook once, not once per ID.
        $userId = $verified->member->user_id;

        if ($userId !== null) {
            MemberBankDetail::whereNull('verified_at')
                ->where('id', '!=', $verified->id)
                ->where('account_number', $verified->account_number)
                ->where('ifsc_code', $verified->ifsc_code)
                ->whereIn('member_id', Member::where('user_id', $userId)->select('id'))
                ->update(['verified_by' => $operator->id, 'verified_at' => $verified->verified_at]);
        }

        if ($verified->member->user !== null) {
            Notifier::toUser($verified->member->user, new BankDetailsVerified($verified));
        }

        return $verified;
    }
}
