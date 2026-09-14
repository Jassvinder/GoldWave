<?php

namespace App\Actions\Payout;

use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\PayoutRequest;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §11.1 — a member can only submit a payout request, never
 * self-withdraw. Validates the configured minimum and available balance,
 * then places the requested amount On Hold immediately (DOMAIN_LOGIC.md §21
 * T-009 pre-coding pass: the hold happens at submission, not deferred to
 * admin review, so a member can't submit multiple requests that together
 * exceed their real balance).
 */
class SubmitPayoutRequest
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly WalletLedgerService $wallet,
    ) {}

    public function __invoke(Member $member, float $amount, MemberBankDetail $bankDetail): PayoutRequest
    {
        $minAmount = (float) $this->rules->value('payout_min_amount', 500);

        if ($amount < $minAmount) {
            throw ValidationException::withMessages([
                'amount' => "The minimum payout request amount is ₹{$minAmount}.",
            ]);
        }

        if ($bankDetail->member_id !== $member->id) {
            throw ValidationException::withMessages([
                'bank_detail' => 'This bank detail does not belong to the requesting member.',
            ]);
        }

        if (! $bankDetail->verified_at) {
            throw ValidationException::withMessages([
                'bank_detail' => 'This bank detail has not been verified yet.',
            ]);
        }

        return DB::transaction(function () use ($member, $amount, $bankDetail) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $available = (float) $locked->wallet_balance - (float) $locked->wallet_hold_amount;

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient available balance for this payout request.',
                ]);
            }

            $request = PayoutRequest::create([
                'member_id' => $locked->id,
                'requested_amount' => $amount,
                'member_bank_detail_id' => $bankDetail->id,
                'status' => 'pending',
            ]);

            $hold = $this->wallet->hold(
                $locked,
                'payout',
                $amount,
                $request,
                "Payout request #{$request->id} hold",
            );

            $request->update(['hold_ledger_entry_id' => $hold->id]);

            return $request->fresh();
        });
    }
}
