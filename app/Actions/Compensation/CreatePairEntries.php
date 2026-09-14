<?php

namespace App\Actions\Compensation;

use App\Models\Member;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Services\BinaryPlacementResolver;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §7 — fans a single "fully eligible joining" out into one
 * `pair_entries` row per Binary Position ancestor (§7's Pair/Reward
 * Beneficiary Chain Rule: a team-size count, unbounded depth, never just the
 * immediate placement parent).
 *
 * A joining becomes "fully eligible" exactly once per member: immediately on
 * a one-time plan's registration payment, or on whichever EMI installment
 * first brings the plan's completed-count to its configured
 * `pair_qualification_emis` threshold (§7.3, Docs/TEST.md scenario 8 — a
 * one-time gate, never re-triggered by later installments).
 *
 * Idempotent: guarded by an upfront check (has this member ever triggered
 * their one-time fan-out before?) and the `(source_payment_id, member_id)` DB
 * unique constraint on `pair_entries`.
 */
class CreatePairEntries
{
    public function __construct(
        private readonly BinaryPlacementResolver $placementChain,
        private readonly RuleVersionService $rules,
    ) {}

    public function __invoke(Payment $payment): void
    {
        $member = $payment->member()->firstOrFail();

        if (PairEntry::whereHas('sourcePayment', fn ($q) => $q->where('member_id', $member->id))->exists()) {
            return;
        }

        if (! $this->isFullyEligibleJoining($member, $payment)) {
            return;
        }

        $chain = $this->placementChain->ancestorsWithSide($member);

        if ($chain === []) {
            return;
        }

        DB::transaction(function () use ($chain, $payment) {
            foreach ($chain as $link) {
                // DOMAIN_LOGIC.md §14.2 point 5: an unassigned dummy (or the
                // seeded company root, which is never assignable) never
                // becomes a compensation beneficiary itself, even though it
                // correctly still occupies a real Binary Position slot for
                // whichever real ancestor sits further up the same chain.
                if ($link['member']->is_company_dummy && $link['member']->dummy_status !== 'assigned') {
                    continue;
                }

                PairEntry::create([
                    'member_id' => $link['member']->id,
                    'side' => $link['side'],
                    'source_payment_id' => $payment->id,
                    'status' => 'unused',
                ]);
            }
        });
    }

    private function isFullyEligibleJoining(Member $member, Payment $payment): bool
    {
        $plan = $member->membershipPlan()->first();

        if (! $plan || ! $plan->isEmiPlan()) {
            return $payment->type === 'registration';
        }

        $schedule = $member->emiSchedule()->first();

        if (! $schedule) {
            return false;
        }

        $requiredEmis = (int) (($this->rules->value('pair_qualification_emis', [])[$plan->code] ?? null) ?? PHP_INT_MAX);
        $completedCount = $schedule->installments()->where('status', 'paid')->count();

        return $completedCount >= $requiredEmis;
    }
}
