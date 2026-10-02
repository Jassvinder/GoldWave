<?php

namespace App\Actions\Compensation;

use App\Models\Member;
use App\Models\PairEntry;
use App\Models\PairRewardTransaction;
use App\Models\RuleVersion;
use App\Services\PairQualifiedDirects;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §7.2/§7.3 — evaluated per beneficiary at month-end: consume
 * only the next unachieved milestone's required Left/Right counts (oldest
 * entries first), pay its reward, and keep climbing while enough unused
 * entries remain in the same run (Docs/TEST.md scenario 2). A milestone whose
 * Left/Right counts are satisfied but whose Min. Direct Members gate isn't
 * met is left entirely alone — nothing is consumed, nothing is paid, and it's
 * simply re-evaluated on a later run once the gate is met (an architecture
 * decision, not a numeric guess: consistent with §7.3's existing
 * carry-forward language, and it never wastes entries a member would
 * otherwise be entitled to).
 *
 * T-178 (29-09-2026) — the gate is a milestone's total of *qualified* directs
 * (`PairQualifiedDirects`, default 2 × milestone no.), Docs/TEST.md scenario 35.
 *
 * Idempotent: `pair_reward_transactions`'s `(member_id, milestone_no)` unique
 * constraint, plus only ever considering milestones above the beneficiary's
 * highest already-achieved one.
 */
class EvaluatePairMilestones
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly WalletLedgerService $wallet,
        private readonly PairQualifiedDirects $qualifiedDirects,
    ) {}

    public function __invoke(Member $beneficiary, Carbon $forMonth): void
    {
        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            return;
        }

        // T-182 — an overdue EMI: entries stay unused and the reward waits for the first month-end after it is paid.
        if ($beneficiary->hasOverdueEmi()) {
            return;
        }

        // T-110 (19-09-2026) — milestone thresholds stay unified/mixed
        // regardless of metal (user's explicit instruction), but the ₹
        // reward no longer comes from one flat rate: a milestone's consumed
        // entries can be a mix of Gold- and Silver-sourced ones, so each
        // metal's own per-entry value is looked up here and applied to only
        // the entries that actually carry that metal (below).
        $payValues = [
            'silver' => (float) $this->rules->value('pair_value_per_entry', 0),
            'gold' => (float) $this->rules->value('pair_value_per_entry_gold', 0),
        ];
        $milestones = collect((array) $this->rules->value('pair_milestones', []))->sortBy('milestone_no')->values();

        $lastAchieved = (int) (PairRewardTransaction::where('member_id', $beneficiary->id)->max('milestone_no') ?? 0);
        $qualifiedDirects = null; // Counted once, only when a milestone's entries are actually met.

        foreach ($milestones->where('milestone_no', '>', $lastAchieved) as $milestone) {
            $unusedLeft = PairEntry::where('member_id', $beneficiary->id)->where('side', 'left')->where('status', 'unused')->count();
            $unusedRight = PairEntry::where('member_id', $beneficiary->id)->where('side', 'right')->where('status', 'unused')->count();

            if ($unusedLeft < $milestone['left'] || $unusedRight < $milestone['right']) {
                break;
            }

            // T-178 — `min_directs` is the milestone's total (default 2 × milestone no.), counted in qualified directs.
            $qualifiedDirects ??= $this->qualifiedDirects->count($beneficiary);

            if ($qualifiedDirects < $milestone['min_directs']) {
                break;
            }

            $this->consumeMilestone($beneficiary, $milestone, $ruleVersion, $payValues, $forMonth);
        }
    }

    /**
     * @param  array{milestone_no: int, min_directs: int, left: int, right: int}  $milestone
     * @param  array{silver: float, gold: float}  $payValues
     */
    private function consumeMilestone(Member $beneficiary, array $milestone, RuleVersion $ruleVersion, array $payValues, Carbon $forMonth): void
    {
        DB::transaction(function () use ($beneficiary, $milestone, $ruleVersion, $payValues, $forMonth) {
            $leftEntries = PairEntry::where('member_id', $beneficiary->id)
                ->where('side', 'left')->where('status', 'unused')
                ->oldest('id')->limit($milestone['left'])->lockForUpdate()->get(['id', 'metal']);

            $rightEntries = PairEntry::where('member_id', $beneficiary->id)
                ->where('side', 'right')->where('status', 'unused')
                ->oldest('id')->limit($milestone['right'])->lockForUpdate()->get(['id', 'metal']);

            $consumedEntries = $leftEntries->merge($rightEntries);

            PairEntry::whereIn('id', $consumedEntries->pluck('id'))->update([
                'status' => 'consumed',
                'consumed_for_milestone_no' => $milestone['milestone_no'],
            ]);

            $reward = round(
                $consumedEntries->sum(fn (PairEntry $entry) => $payValues[$entry->metal] ?? $payValues['silver']),
                2,
            );

            $transaction = PairRewardTransaction::create([
                'member_id' => $beneficiary->id,
                'milestone_no' => $milestone['milestone_no'],
                'left_consumed_count' => $milestone['left'],
                'right_consumed_count' => $milestone['right'],
                'reward_amount' => $reward,
                'rule_version_id' => $ruleVersion->id,
                'calculated_for_month' => $forMonth->toDateString(),
            ]);

            $this->wallet->credit(
                $beneficiary,
                'pair_reward',
                $reward,
                $transaction,
                "Pair/Reward milestone {$milestone['milestone_no']}",
            );
        });
    }
}
