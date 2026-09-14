<?php

namespace App\Actions\Compensation;

use App\Models\BoosterQualification;
use App\Models\Member;
use App\Models\Payment;
use App\Models\RuleVersion;
use App\Services\BinaryPlacementResolver;
use App\Services\BinaryTeamSizeCounter;
use App\Services\RuleVersionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §9/§9.1 — Income Booster. Only a `registration` payment
 * triggers evaluation (DOMAIN_LOGIC.md §21 T-011 pre-coding pass): Booster's
 * inputs (Direct count, binary team size) are structural and only change
 * when a new member is placed into the tree, never on a later EMI
 * installment. Walks the new member's full Binary Position ancestor chain
 * (unbounded depth, same as Pair/Reward's §7 chain) and evaluates every
 * booster level each ancestor newly qualifies for — not only the highest
 * (§9.1 step 3, concurrency rule). Qualification is a one-time gate per
 * level; a 6-month payout schedule is created per newly-qualified level,
 * never re-triggered once a member already holds that level's row (DB
 * unique constraint on (member_id, level_no) backs this up).
 */
class EvaluateBoosterQualification
{
    public function __construct(
        private readonly BinaryPlacementResolver $placementChain,
        private readonly BinaryTeamSizeCounter $teamSizeCounter,
        private readonly RuleVersionService $rules,
    ) {}

    public function __invoke(Payment $payment): void
    {
        if ($payment->type !== 'registration') {
            return;
        }

        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            return;
        }

        $levels = $this->rules->value('booster_levels', []);

        if ($levels === []) {
            return;
        }

        $member = $payment->member()->firstOrFail();
        // Booster's Level 3 threshold alone is a 3,000-member team — the
        // occupied-side traversal algorithm (DOMAIN_LOGIC.md §4.3) can
        // legitimately produce chains far deeper than BinaryPlacementResolver's
        // 500-level "corrupted data" trip-wire default if members
        // disproportionately favor one side, so this call raises it well
        // past any realistic depth rather than silently truncating the
        // ancestor walk for a large, genuinely deep team.
        $ancestors = $this->placementChain->ancestorsWithSide($member, 10000);

        if ($ancestors === []) {
            return;
        }

        DB::transaction(function () use ($ancestors, $levels, $ruleVersion) {
            foreach ($ancestors as $link) {
                // DOMAIN_LOGIC.md §14.2 point 5: an unassigned dummy (or the
                // seeded company root, which is never assignable) never
                // becomes a compensation beneficiary itself, even though it
                // correctly still occupies a real Binary Position slot for
                // whichever real ancestor sits further up the same chain.
                if ($link['member']->is_company_dummy && $link['member']->dummy_status !== 'assigned') {
                    continue;
                }

                $this->evaluateForAncestor($link['member'], $levels, $ruleVersion);
            }
        });
    }

    /** @param array<int, array<string, mixed>> $levels */
    private function evaluateForAncestor(Member $ancestor, array $levels, RuleVersion $ruleVersion): void
    {
        $alreadyQualifiedLevels = BoosterQualification::where('member_id', $ancestor->id)->pluck('level_no')->all();
        $directCount = $ancestor->directs()->count();
        $teamSides = $this->teamSizeCounter->countSides($ancestor);

        foreach ($levels as $level) {
            $levelNo = (int) $level['level_no'];

            if (in_array($levelNo, $alreadyQualifiedLevels, true)) {
                continue;
            }

            $meetsDirects = $directCount >= (int) $level['min_directs'];
            $meetsSplit = $teamSides['left'] >= (int) $level['team_split_left']
                && $teamSides['right'] >= (int) $level['team_split_right'];

            if (! $meetsDirects || ! $meetsSplit) {
                continue;
            }

            $this->createQualificationAndSchedule($ancestor, $levelNo, $level, $ruleVersion);
        }
    }

    /** @param array<string, mixed> $level */
    private function createQualificationAndSchedule(Member $ancestor, int $levelNo, array $level, RuleVersion $ruleVersion): void
    {
        $qualification = BoosterQualification::create([
            'member_id' => $ancestor->id,
            'level_no' => $levelNo,
            'qualified_at' => now(),
            'rule_version_id' => $ruleVersion->id,
        ]);

        $duration = (int) $level['duration_months'];
        $monthlyAmount = (float) $level['monthly_benefit'];
        $qualifiedAt = Carbon::parse($qualification->qualified_at);

        for ($month = 1; $month <= $duration; $month++) {
            $qualification->payoutSchedules()->create([
                'month_no' => $month,
                'scheduled_date' => $qualifiedAt->copy()->addMonthsNoOverflow($month - 1)->toDateString(),
                'amount' => $monthlyAmount,
                'status' => 'pending',
            ]);
        }
    }
}
