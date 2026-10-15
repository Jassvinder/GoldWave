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
            // 15-10-2026 — a cash approval for a member 553 levels deep took 35 s: every ancestor ran its own
            // qualification and Direct-count queries plus a full recursive team count. Both cheap inputs are now read
            // for the whole chain at once, and the team is counted only for an ancestor with enough Directs for a level
            // it doesn't hold yet. The outcome is unchanged.
            $ancestorIds = array_map(fn (array $link): int => $link['member']->id, $ancestors);
            [$qualifiedLevels, $directCounts] = $this->chainFacts($ancestorIds);

            foreach ($ancestors as $link) {
                // DOMAIN_LOGIC.md §14.2 point 5: an unassigned dummy (or the
                // seeded company root, which is never assignable) never
                // becomes a compensation beneficiary itself, even though it
                // correctly still occupies a real Binary Position slot for
                // whichever real ancestor sits further up the same chain.
                if ($link['member']->is_company_dummy && $link['member']->dummy_status !== 'assigned') {
                    continue;
                }

                $id = $link['member']->id;
                $this->evaluateForAncestor($link['member'], $levels, $ruleVersion, $qualifiedLevels[$id] ?? [], $directCounts[$id] ?? 0);
            }
        });
    }

    /**
     * Every ancestor's already-qualified levels and Direct count, read in a few queries for the whole chain.
     *
     * @param  list<int>  $ancestorIds
     * @return array{0: array<int, list<int>>, 1: array<int, int>}
     */
    private function chainFacts(array $ancestorIds): array
    {
        $qualifiedLevels = [];
        $directCounts = [];

        foreach (array_chunk($ancestorIds, 1000) as $chunk) {
            foreach (BoosterQualification::whereIn('member_id', $chunk)->get(['member_id', 'level_no']) as $row) {
                $qualifiedLevels[(int) $row->member_id][] = (int) $row->level_no;
            }

            $counts = Member::whereIn('sponsor_id', $chunk)
                ->groupBy('sponsor_id')
                ->selectRaw('sponsor_id, COUNT(*) AS directs')
                ->pluck('directs', 'sponsor_id');

            foreach ($counts as $sponsorId => $count) {
                $directCounts[(int) $sponsorId] = (int) $count;
            }
        }

        return [$qualifiedLevels, $directCounts];
    }

    /**
     * @param  array<int, array<string, mixed>>  $levels
     * @param  list<int>  $alreadyQualifiedLevels
     */
    private function evaluateForAncestor(Member $ancestor, array $levels, RuleVersion $ruleVersion, array $alreadyQualifiedLevels, int $directCount): void
    {
        // Levels still open to this ancestor on Directs alone; the team split needs the (expensive) team count.
        $candidates = array_filter($levels, fn (array $level): bool => ! in_array((int) $level['level_no'], $alreadyQualifiedLevels, true)
            && $directCount >= (int) $level['min_directs']);

        if ($candidates === []) {
            return;
        }

        $teamSides = $this->teamSizeCounter->countSides($ancestor);

        foreach ($candidates as $level) {
            $meetsSplit = $teamSides['left'] >= (int) $level['team_split_left']
                && $teamSides['right'] >= (int) $level['team_split_right'];

            if (! $meetsSplit) {
                continue;
            }

            $this->createQualificationAndSchedule($ancestor, (int) $level['level_no'], $level, $ruleVersion);
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
