<?php

use App\Actions\Payout\CancelPayoutRequest;
use App\Actions\Payout\FailPayoutRequest;
use App\Actions\Payout\ProcessPayoutRequest;
use App\Actions\Payout\RejectPayoutRequest;
use App\Actions\Payout\SubmitPayoutRequest;
use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\PayoutRequest;
use App\Models\PayoutTransaction;
use App\Models\RuleValue;
use App\Models\User;
use App\Services\WalletLedgerService;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §11 (Payment Out / Payout), Docs/TEST.md scenario 7 — the
 * full request → hold → processing → TDS/fee deduction → reject/cancel →
 * bulk flow, with the scenario's exact worked numbers.
 */
function payoutMember(string $customerId, float $walletBalance = 0): Member
{
    $user = User::factory()->create(['role' => 'member']);

    $member = Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);

    if ($walletBalance > 0) {
        app(WalletLedgerService::class)->credit($member, 'level_income', $walletBalance, null, 'seed credit');
    }

    return $member->fresh();
}

function verifiedBankDetail(Member $member): MemberBankDetail
{
    return MemberBankDetail::create([
        'member_id' => $member->id,
        'account_holder_name' => 'Test Holder',
        'account_number' => '1234567890',
        'ifsc_code' => 'TEST0001234',
        'bank_name' => 'Test Bank',
        'verified_at' => now(),
    ]);
}

function superAdmin(): User
{
    return User::factory()->create(['role' => 'super_admin']);
}

beforeEach(function () {
    $this->seed();
});

test('submitting a request places a wallet hold without touching the spendable balance', function () {
    $member = payoutMember('PO-SUBMIT-1', 12000);
    $bankDetail = verifiedBankDetail($member);

    $request = app(SubmitPayoutRequest::class)($member->fresh(), 10000, $bankDetail);

    expect($request->status)->toBe('pending');
    expect((float) $request->requested_amount)->toBe(10000.0);
    expect($request->hold_ledger_entry_id)->not->toBeNull();
    expect($request->holdLedgerEntry->entry_type)->toBe('debit');
    expect($request->holdLedgerEntry->status)->toBe('pending');

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(12000.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(10000.0);
    expect(app(WalletLedgerService::class)->availableBalance($fresh))->toBe(2000.0);
});

test('a request below the configured minimum is rejected with no row and no hold', function () {
    $member = payoutMember('PO-MIN-1', 12000);
    $bankDetail = verifiedBankDetail($member);

    expect(fn () => app(SubmitPayoutRequest::class)($member->fresh(), 100, $bankDetail))
        ->toThrow(ValidationException::class);

    expect(PayoutRequest::where('member_id', $member->id)->count())->toBe(0);
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(0.0);
});

test('a request beyond the available balance is rejected with no row and no hold', function () {
    $member = payoutMember('PO-BAL-1', 8000);
    $bankDetail = verifiedBankDetail($member);

    expect(fn () => app(SubmitPayoutRequest::class)($member->fresh(), 10000, $bankDetail))
        ->toThrow(ValidationException::class);

    expect(PayoutRequest::where('member_id', $member->id)->count())->toBe(0);
    expect((float) $member->fresh()->wallet_hold_amount)->toBe(0.0);
});

test('processing deducts TDS and processing fee, confirms the hold, and matches TEST.md scenario 7', function () {
    RuleValue::where('key', 'payout_tds_percent')->update(['value' => 5]);
    RuleValue::where('key', 'payout_processing_fee_percent')->update(['value' => 1]);

    $member = payoutMember('PO-PROCESS-1', 12000);
    $bankDetail = verifiedBankDetail($member);
    $request = app(SubmitPayoutRequest::class)($member->fresh(), 10000, $bankDetail);
    $operator = superAdmin();

    $transaction = app(ProcessPayoutRequest::class)($request, $operator, 'bank_transfer', 'UTR12345');

    expect((float) $transaction->amount_snapshot)->toBe(10000.0);
    expect((float) $transaction->tds_amount)->toBe(500.0);
    expect((float) $transaction->processing_fee)->toBe(100.0);
    expect($transaction->method)->toBe('bank_transfer');
    expect($transaction->reference)->toBe('UTR12345');
    expect($transaction->status)->toBe('processed');
    expect($transaction->netAmount())->toBe(9400.0);

    expect($request->fresh()->status)->toBe('processed');

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(2000.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
});

test('at the real default TDS/fee of 0%, the net transfer equals the full requested amount exactly', function () {
    $member = payoutMember('PO-PROCESS-2', 10000);
    $bankDetail = verifiedBankDetail($member);
    $request = app(SubmitPayoutRequest::class)($member->fresh(), 10000, $bankDetail);
    $operator = superAdmin();

    $transaction = app(ProcessPayoutRequest::class)($request, $operator, 'gpay_upi', 'GPAY-1');

    expect((float) $transaction->tds_amount)->toBe(0.0);
    expect((float) $transaction->processing_fee)->toBe(0.0);
    expect($transaction->netAmount())->toBe(10000.0);
});

test('reject releases the hold with no payout_transactions row', function () {
    $member = payoutMember('PO-REJECT-1', 8000);
    $bankDetail = verifiedBankDetail($member);
    $request = app(SubmitPayoutRequest::class)($member->fresh(), 3000, $bankDetail);

    $rejected = app(RejectPayoutRequest::class)($request);

    expect($rejected->status)->toBe('rejected');
    expect(PayoutTransaction::where('payout_request_id', $request->id)->count())->toBe(0);

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
    expect((float) $fresh->wallet_balance)->toBe(8000.0);
});

test('cancel has the identical wallet effect as reject, differing only in status', function () {
    $member = payoutMember('PO-CANCEL-1', 8000);
    $bankDetail = verifiedBankDetail($member);
    $request = app(SubmitPayoutRequest::class)($member->fresh(), 3000, $bankDetail);

    $cancelled = app(CancelPayoutRequest::class)($request);

    expect($cancelled->status)->toBe('cancelled');
    expect(PayoutTransaction::where('payout_request_id', $request->id)->count())->toBe(0);

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
    expect((float) $fresh->wallet_balance)->toBe(8000.0);
});

test('a terminal (processed) request can never be rejected or cancelled', function () {
    $member = payoutMember('PO-TERMINAL-1', 5000);
    $bankDetail = verifiedBankDetail($member);
    $request = app(SubmitPayoutRequest::class)($member->fresh(), 2000, $bankDetail);
    app(ProcessPayoutRequest::class)($request, superAdmin(), 'cheque', 'CHQ-1');

    expect(fn () => app(RejectPayoutRequest::class)($request->fresh()))->toThrow(ValidationException::class);
    expect(fn () => app(CancelPayoutRequest::class)($request->fresh()))->toThrow(ValidationException::class);
    expect(fn () => app(ProcessPayoutRequest::class)($request->fresh(), superAdmin(), 'cheque', 'CHQ-2'))
        ->toThrow(ValidationException::class);
});

test('bulk processing shares one batch_reference across independent per-request transactions', function () {
    $operator = superAdmin();
    $batchReference = 'BATCH-2026-09-13';

    $memberA = payoutMember('PO-BULK-A', 5000);
    $requestA = app(SubmitPayoutRequest::class)($memberA->fresh(), 2000, verifiedBankDetail($memberA));

    $memberB = payoutMember('PO-BULK-B', 6000);
    $requestB = app(SubmitPayoutRequest::class)($memberB->fresh(), 3500, verifiedBankDetail($memberB));

    $memberC = payoutMember('PO-BULK-C', 3000);
    $requestC = app(SubmitPayoutRequest::class)($memberC->fresh(), 1200, verifiedBankDetail($memberC));

    // Simulate requestB's hold already being released concurrently (e.g. rejected) before the batch runs.
    app(RejectPayoutRequest::class)($requestB);

    $results = [];
    foreach ([$requestA, $requestB, $requestC] as $request) {
        try {
            $results[] = app(ProcessPayoutRequest::class)($request->fresh(), $operator, 'bank_transfer', "REF-{$request->id}", $batchReference);
        } catch (ValidationException) {
            $results[] = null;
        }
    }

    expect($results[0])->not->toBeNull();
    expect($results[1])->toBeNull(); // Already rejected — skipped without blocking the others.
    expect($results[2])->not->toBeNull();

    expect($requestA->fresh()->status)->toBe('processed');
    expect($requestB->fresh()->status)->toBe('rejected');
    expect($requestC->fresh()->status)->toBe('processed');

    expect(PayoutTransaction::where('batch_reference', $batchReference)->count())->toBe(2);
    expect(PayoutTransaction::where('batch_reference', $batchReference)->pluck('amount_snapshot')->map(fn ($v) => (float) $v)->all())
        ->toEqualCanonicalizing([2000.0, 1200.0]);
});

test('a failed payout still records an audit transaction row but releases the hold instead of confirming it', function () {
    $member = payoutMember('PO-FAIL-1', 5000);
    $bankDetail = verifiedBankDetail($member);
    $request = app(SubmitPayoutRequest::class)($member->fresh(), 2000, $bankDetail);
    $operator = superAdmin();

    $transaction = app(FailPayoutRequest::class)($request, $operator, 'bank_transfer', 'UTR99999');

    expect($transaction->status)->toBe('failed');
    expect($transaction->method)->toBe('bank_transfer');
    expect($transaction->reference)->toBe('UTR99999');

    expect($request->fresh()->status)->toBe('failed');

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(5000.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
});
