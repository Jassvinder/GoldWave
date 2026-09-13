<?php

namespace App\Services;

use App\Models\Member;
use App\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ARCHITECTURE.md: the ONLY class allowed to write `wallet_ledger_entries` or
 * mutate `members.wallet_balance` — this is what makes DOMAIN_LOGIC.md §12's
 * "never update a wallet balance without an associated ledger transaction"
 * structurally true instead of a convention someone can forget.
 *
 * The credit() side was front-loaded in T-006 (needed by CalculateLevelIncome
 * per Compensation Engine rule 3), following the same
 * front-load-when-first-needed pattern already used for SponsorChainResolver
 * (T-004) and RuleVersionService (T-003). T-008 completes this class with the
 * hold()/confirmHold()/releaseHold() debit-side primitives DOMAIN_LOGIC.md
 * §11.1's Wallet Hold rule needs — T-009 (the actual Payout request/
 * processing workflow) calls these rather than building its own debit logic.
 */
class WalletLedgerService
{
    /**
     * Credit a member's wallet, atomically writing the audit ledger row and
     * updating the cached balance together. Never called with an amount a
     * caller hasn't already decided is payable — this method does not
     * evaluate eligibility itself.
     */
    public function credit(
        Member $member,
        string $category,
        float $amount,
        ?Model $source,
        string $description,
    ): WalletLedgerEntry {
        return DB::transaction(function () use ($member, $category, $amount, $source, $description) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            $entry = WalletLedgerEntry::create([
                'member_id' => $locked->id,
                'entry_type' => 'credit',
                'category' => $category,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'amount' => $amount,
                'status' => 'confirmed',
                'description' => $description,
                'processed_at' => now(),
            ]);

            $locked->increment('wallet_balance', $amount);

            return $entry;
        });
    }

    /**
     * Place an amount On Hold (DOMAIN_LOGIC.md §11.1) — reserves it against
     * `members.wallet_hold_amount` without touching the spendable
     * `wallet_balance` yet. Creates the `debit`/`status=pending` ledger row a
     * payout request links to (`payout_requests.hold_ledger_entry_id`);
     * `confirmHold()`/`releaseHold()` complete that same row's lifecycle
     * rather than creating a second row — DOMAIN_LOGIC.md §22's "never delete
     * financial history, reverse/correct through linked transactions only"
     * governs fixing a mistake after the fact, not a transaction's own
     * declared pending→confirmed/reversed lifecycle, which
     * `wallet_ledger_entries.status`/`processed_at` exist specifically to
     * carry.
     */
    public function hold(
        Member $member,
        string $category,
        float $amount,
        ?Model $source,
        string $description,
    ): WalletLedgerEntry {
        return DB::transaction(function () use ($member, $category, $amount, $source, $description) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            $entry = WalletLedgerEntry::create([
                'member_id' => $locked->id,
                'entry_type' => 'debit',
                'category' => $category,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'amount' => $amount,
                'status' => 'pending',
                'description' => $description,
            ]);

            $locked->increment('wallet_hold_amount', $amount);

            return $entry;
        });
    }

    /**
     * Finalize a pending hold as an actual debit: releases the hold and
     * decrements the spendable balance by the same amount. Idempotent — a
     * hold that isn't currently `pending` is a no-op, so a retried payout
     * confirmation can never double-debit.
     */
    public function confirmHold(WalletLedgerEntry $hold): void
    {
        DB::transaction(function () use ($hold) {
            $lockedEntry = WalletLedgerEntry::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($lockedEntry->status !== 'pending') {
                return;
            }

            $lockedEntry->update(['status' => 'confirmed', 'processed_at' => now()]);

            $lockedMember = Member::whereKey($lockedEntry->member_id)->lockForUpdate()->firstOrFail();
            $lockedMember->decrement('wallet_hold_amount', (float) $lockedEntry->amount);
            $lockedMember->decrement('wallet_balance', (float) $lockedEntry->amount);
        });
    }

    /**
     * Release a pending hold without debiting anything — DOMAIN_LOGIC.md
     * §11.1: released when a payout request is rejected/cancelled or fails.
     * Idempotent, same as `confirmHold()`.
     */
    public function releaseHold(WalletLedgerEntry $hold): void
    {
        DB::transaction(function () use ($hold) {
            $lockedEntry = WalletLedgerEntry::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($lockedEntry->status !== 'pending') {
                return;
            }

            $lockedEntry->update(['status' => 'reversed', 'processed_at' => now()]);

            Member::whereKey($lockedEntry->member_id)->lockForUpdate()->firstOrFail()
                ->decrement('wallet_hold_amount', (float) $lockedEntry->amount);
        });
    }

    /**
     * Spendable balance a member could request a new payout against —
     * `wallet_balance` minus whatever is currently On Hold for other
     * in-progress requests.
     */
    public function availableBalance(Member $member): float
    {
        return (float) $member->wallet_balance - (float) $member->wallet_hold_amount;
    }
}
