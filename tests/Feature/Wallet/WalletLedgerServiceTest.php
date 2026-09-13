<?php

use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\WalletLedgerService;

/**
 * DOMAIN_LOGIC.md §12 (Wallet/Ledger) and §11.1 (Wallet Hold). T-008
 * completes `WalletLedgerService` with the debit-side hold/confirm/release
 * primitives — the credit() side was already built and tested in T-006.
 */
function walletMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->wallet = app(WalletLedgerService::class);
});

test('hold() reserves an amount without touching the spendable balance', function () {
    $member = walletMember('WL-HOLD-1');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');

    $entry = $this->wallet->hold($member, 'payout', 400, null, 'payout hold');

    expect($entry->entry_type)->toBe('debit');
    expect($entry->status)->toBe('pending');
    expect((float) $entry->amount)->toBe(400.0);
    expect($entry->processed_at)->toBeNull();

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(1000.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(400.0);
    expect($this->wallet->availableBalance($fresh))->toBe(600.0);
});

test('confirmHold() finalizes the hold into an actual debit', function () {
    $member = walletMember('WL-CONFIRM-1');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');
    $hold = $this->wallet->hold($member, 'payout', 400, null, 'payout hold');

    $this->wallet->confirmHold($hold);

    expect($hold->fresh()->status)->toBe('confirmed');
    expect($hold->fresh()->processed_at)->not->toBeNull();

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(600.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
    expect($this->wallet->availableBalance($fresh))->toBe(600.0);
});

test('confirmHold() is idempotent — a second call never double-debits', function () {
    $member = walletMember('WL-CONFIRM-2');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');
    $hold = $this->wallet->hold($member, 'payout', 400, null, 'payout hold');

    $this->wallet->confirmHold($hold);
    $this->wallet->confirmHold($hold->fresh());

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(600.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
});

test('releaseHold() cancels a hold without debiting anything', function () {
    $member = walletMember('WL-RELEASE-1');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');
    $hold = $this->wallet->hold($member, 'payout', 400, null, 'payout hold');

    $this->wallet->releaseHold($hold);

    expect($hold->fresh()->status)->toBe('reversed');
    expect($hold->fresh()->processed_at)->not->toBeNull();

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(1000.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
    expect($this->wallet->availableBalance($fresh))->toBe(1000.0);
});

test('releaseHold() is idempotent — a second call changes nothing further', function () {
    $member = walletMember('WL-RELEASE-2');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');
    $hold = $this->wallet->hold($member, 'payout', 400, null, 'payout hold');

    $this->wallet->releaseHold($hold);
    $this->wallet->releaseHold($hold->fresh());

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(1000.0);
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
});

test('a confirmed hold can never be released, and a released hold can never be confirmed', function () {
    $member = walletMember('WL-CROSS-1');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');

    $confirmedHold = $this->wallet->hold($member, 'payout', 100, null, 'a');
    $this->wallet->confirmHold($confirmedHold);
    $this->wallet->releaseHold($confirmedHold->fresh());
    expect($confirmedHold->fresh()->status)->toBe('confirmed');

    $releasedHold = $this->wallet->hold($member, 'payout', 100, null, 'b');
    $this->wallet->releaseHold($releasedHold);
    $this->wallet->confirmHold($releasedHold->fresh());
    expect($releasedHold->fresh()->status)->toBe('reversed');

    $fresh = $member->fresh();
    expect((float) $fresh->wallet_balance)->toBe(900.0); // 1000 - 100 (confirmed only)
    expect((float) $fresh->wallet_hold_amount)->toBe(0.0);
});

test('multiple concurrent holds each reduce available balance independently', function () {
    $member = walletMember('WL-MULTI-1');
    $this->wallet->credit($member, 'level_income', 1000, null, 'seed credit');

    $holdA = $this->wallet->hold($member, 'payout', 300, null, 'a');
    $holdB = $this->wallet->hold($member, 'payout', 200, null, 'b');

    expect($this->wallet->availableBalance($member->fresh()))->toBe(500.0);

    $this->wallet->confirmHold($holdA);
    expect($this->wallet->availableBalance($member->fresh()))->toBe(500.0); // 700 balance - 200 still held.

    $this->wallet->releaseHold($holdB);
    expect($this->wallet->availableBalance($member->fresh()))->toBe(700.0);
    expect((float) $member->fresh()->wallet_balance)->toBe(700.0);
});

test('a wallet_ledger_entries row for a hold links back to its source model', function () {
    $member = walletMember('WL-SOURCE-1');
    $sourcePayment = Payment::create([
        'member_id' => $member->id,
        'type' => 'registration',
        'amount' => 1,
        'mode' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $hold = $this->wallet->hold($member, 'payout', 100, $sourcePayment, 'linked hold');

    expect($hold->source_type)->toBe($sourcePayment->getMorphClass());
    expect($hold->source_id)->toBe($sourcePayment->id);
    expect(WalletLedgerEntry::where('member_id', $member->id)->where('status', 'pending')->count())->toBe(1);
});
