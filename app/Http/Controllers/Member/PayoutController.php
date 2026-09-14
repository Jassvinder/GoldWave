<?php

namespace App\Http\Controllers\Member;

use App\Actions\Payout\SubmitPayoutRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payout\SubmitPayoutRequestRequest;
use App\Models\PayoutRequest;
use App\Models\PayoutTransaction;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M15/M16 — request/track withdrawals (DOMAIN_LOGIC.md §11). */
class PayoutController extends Controller
{
    public function __construct(
        private readonly WalletLedgerService $wallet,
        private readonly RuleVersionService $rules,
    ) {}

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $bankDetail = $member->bankDetails()->latest('id')->first();

        $requests = $member->payoutRequests()
            ->with('transactions')
            ->orderByDesc('id')
            ->get()
            ->map($this->mapPayoutRequest(...));

        return Inertia::render('member/payout', [
            'available_balance' => (string) $this->wallet->availableBalance($member),
            'min_amount' => (string) $this->rules->value('payout_min_amount', 500),
            'bank_detail' => $bankDetail ? [
                'id' => $bankDetail->id,
                'bank_name' => $bankDetail->bank_name,
                'account_number' => $bankDetail->account_number,
                'verified_at' => Dates::date($bankDetail->verified_at),
            ] : null,
            'requests' => $requests,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapPayoutRequest(PayoutRequest $payoutRequest): array
    {
        return [
            'id' => $payoutRequest->id,
            'requested_amount' => $payoutRequest->requested_amount,
            'status' => $payoutRequest->status,
            'created_at' => Dates::date($payoutRequest->created_at),
            'transactions' => $payoutRequest->transactions->map($this->mapTransaction(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function mapTransaction(PayoutTransaction $transaction): array
    {
        return [
            'method' => $transaction->method,
            'reference' => $transaction->reference,
            'net_amount' => $transaction->netAmount(),
            'status' => $transaction->status,
            'processed_at' => Dates::date($transaction->processed_at),
        ];
    }

    public function store(SubmitPayoutRequestRequest $request, SubmitPayoutRequest $action): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $bankDetail = $member->bankDetails()->latest('id')->first();

        if (! $bankDetail) {
            throw ValidationException::withMessages([
                'amount' => 'Complete your Pending Profile fields to add bank details before requesting a payout.',
            ]);
        }

        $action($member, (float) $request->input('amount'), $bankDetail);

        return redirect()->route('member.payout.index')
            ->with('status', 'Payout request submitted.');
    }
}
