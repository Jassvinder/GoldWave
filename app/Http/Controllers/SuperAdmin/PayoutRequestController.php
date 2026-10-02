<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Payout\CancelPayoutRequest;
use App\Actions\Payout\FailPayoutRequest;
use App\Actions\Payout\ProcessPayoutRequest;
use App\Actions\Payout\RejectPayoutRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RecordPayoutOutcomeRequest;
use App\Models\MemberBankDetail;
use App\Models\PayoutRequest;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-109 (17-09-2026), user-reported — `ProcessPayoutRequest`/
 * `FailPayoutRequest`/`RejectPayoutRequest` have existed since T-009 with
 * no Super Admin page ever wired to them; Payout & TDS Settings is rate
 * configuration, not this queue. Mirrors `CashPaymentApprovalController`'s
 * pending-only-queue shape.
 */
class PayoutRequestController extends Controller
{
    public function index(): Response
    {
        $pending = PayoutRequest::with(['member.user', 'bankDetail'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PayoutRequest $request): array => [
                'id' => $request->id,
                'requested_amount' => $request->requested_amount,
                'created_at' => Dates::date($request->created_at),
                'member' => [
                    'customer_id' => $request->member->customer_id,
                    'name' => $request->member->user?->name,
                ],
                'bank_detail' => $request->bankDetail ? [
                    'account_holder_name' => $request->bankDetail->account_holder_name,
                    'account_number' => $request->bankDetail->account_number,
                    'ifsc_code' => $request->bankDetail->ifsc_code,
                    'bank_name' => $request->bankDetail->bank_name,
                    'verified_at' => Dates::date($request->bankDetail->verified_at),
                ] : null,
            ]);

        // Everything that has left the queue — processed, rejected, failed or cancelled — so a
        // decided payout never simply vanishes from the Super Admin's view.
        $history = PayoutRequest::with(['member.user', 'transactions.processedBy'])
            ->where('status', '!=', 'pending')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayoutRequest $request): array => $this->mapHistory($request));

        // T-195 — a member can't request a payout until their bank details are verified, so the members waiting on
        // that are shown here too (verification itself stays on Member Detail).
        $awaitingBankVerification = MemberBankDetail::with('member.user')
            ->whereNull('verified_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MemberBankDetail $detail): array => [
                'member_id' => $detail->member_id,
                'customer_id' => $detail->member->customer_id,
                'name' => $detail->member->user?->name,
                'bank_name' => $detail->bank_name,
                'account_last4' => substr((string) $detail->account_number, -4),
                'submitted_on' => Dates::date($detail->created_at),
                'wallet_balance' => (string) $detail->member->wallet_balance,
            ]);

        return Inertia::render('super-admin/payout-requests', [
            'pending' => $pending,
            'history' => $history,
            'awaiting_bank_verification' => $awaitingBankVerification,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapHistory(PayoutRequest $request): array
    {
        // A failed attempt and a later retry are not possible today (failed is terminal), so the latest transaction is the outcome.
        $transaction = $request->transactions->sortByDesc('id')->first();

        return [
            'id' => $request->id,
            'status' => $request->status,
            'requested_amount' => $request->requested_amount,
            'requested_at' => Dates::date($request->created_at),
            'decided_at' => Dates::date($transaction->processed_at ?? $request->updated_at),
            'member' => [
                'customer_id' => $request->member->customer_id,
                'name' => $request->member->user?->name,
            ],
            'transaction' => $transaction ? [
                'method' => $transaction->methodLabel(),
                'reference' => $transaction->reference,
                'batch_reference' => $transaction->batch_reference,
                'tds_amount' => $transaction->tds_amount,
                'processing_fee' => $transaction->processing_fee,
                'net_amount' => number_format($transaction->netAmount(), 2, '.', ''),
                'bank_name' => $transaction->beneficiary_snapshot['bank_name'] ?? null,
                'account_number' => $transaction->beneficiary_snapshot['account_number'] ?? null,
                'processed_by' => $transaction->processedBy->name,
            ] : null,
        ];
    }

    public function process(RecordPayoutOutcomeRequest $request, PayoutRequest $payout_request, ProcessPayoutRequest $action): RedirectResponse
    {
        $action(
            $payout_request,
            $request->user(),
            $request->string('method')->toString(),
            $request->string('reference')->toString() ?: null,
        );

        return back()->with('status', 'Payout processed.');
    }

    public function fail(RecordPayoutOutcomeRequest $request, PayoutRequest $payout_request, FailPayoutRequest $action): RedirectResponse
    {
        $action(
            $payout_request,
            $request->user(),
            $request->string('method')->toString(),
            $request->string('reference')->toString() ?: null,
        );

        return back()->with('status', 'Payout marked failed; hold released.');
    }

    public function reject(PayoutRequest $payout_request, RejectPayoutRequest $action): RedirectResponse
    {
        $action($payout_request);

        return back()->with('status', 'Payout request rejected.');
    }

    public function cancel(PayoutRequest $payout_request, CancelPayoutRequest $action): RedirectResponse
    {
        $action($payout_request);

        return back()->with('status', 'Payout request cancelled.');
    }
}
