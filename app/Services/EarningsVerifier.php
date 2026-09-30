<?php

namespace App\Services;

use App\Models\IncomeLedgerCalculation;
use App\Models\PairRewardTransaction;
use App\Models\RuleValue;
use App\Models\StoreProfitDistribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Earnings verification — an independent re-computation of every stored earning from the source events, compared with what
 * the compensation Actions actually wrote. It deliberately does NOT call `CalculateLevelIncome`, `CalculatePairMilestones`
 * & co.: it re-derives the expected rows straight from `members`, `payments`, `store_sales` and the rule values recorded on
 * each stored row, so a bug in an Action (or a row changed by hand) shows up as a difference here.
 *
 * Findings are `error` (a real inconsistency) or `warning` (something that can legitimately differ, e.g. a beneficiary's
 * status changed after the payment). Read-only: nothing is ever written or corrected.
 *
 * Checks: `level` Level Income, `purchase` Purchase/Repurchase Income, `store` Store Profit Distribution, `pair` Pair
 * entries and Pair/Reward, `booster` Booster payout schedules, `ledger` every credited earning has exactly one wallet
 * entry and vice versa, `wallet` each member's cached balance equals their ledger. **Not re-derived:** whether a member
 * qualified for a Booster level, and Monthly Draw eligibility/winners (only the payouts that were created are checked).
 */
class EarningsVerifier
{
    /** @var array<string, string> */
    public const CHECKS = [
        'level' => 'Level Income',
        'purchase' => 'Purchase / Repurchase Income',
        'store' => 'Store Profit Distribution',
        'pair' => 'Pair entries and Pair/Reward',
        'booster' => 'Income Booster payouts',
        'ledger' => 'Wallet ledger links',
        'wallet' => 'Wallet balances',
    ];

    private const MAX_LEVELS = 12;

    /** @var Collection<int, stdClass> */
    private Collection $members;

    /** @var Collection<int, stdClass> */
    private Collection $plans;

    /** @var array<string, mixed> */
    private array $ruleCache = [];

    /** @var array<int, int> qualified-direct count per member, per run (T-178/T-179) */
    private array $qualifiedDirectCounts = [];

    /** @var array<string, array{label: string, checked: int, errors: int, warnings: int, findings: list<array{severity: string, message: string, ref: string}>}> */
    private array $report = [];

    private int $limit = 50;

    /**
     * @param  list<string>  $only  check keys to run; empty = all
     * @return array{ran_at: string, errors: int, warnings: int, checks: array<string, array{label: string, checked: int, errors: int, warnings: int, findings: list<array{severity: string, message: string, ref: string}>}>}
     */
    public function run(array $only = [], int $limit = 50): array
    {
        $this->limit = $limit;
        $this->report = [];
        $this->ruleCache = [];
        $this->qualifiedDirectCounts = [];
        $this->members = DB::table('members')->get()->keyBy('id');
        $this->plans = DB::table('membership_plans')->get()->keyBy('id');

        foreach (self::CHECKS as $key => $label) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $this->report[$key] = ['label' => $label, 'checked' => 0, 'errors' => 0, 'warnings' => 0, 'findings' => []];

            match ($key) {
                'level' => $this->checkLevelIncome(),
                'purchase' => $this->checkPurchaseRepurchase(),
                'store' => $this->checkStoreProfit(),
                'pair' => $this->checkPair(),
                'booster' => $this->checkBooster(),
                'ledger' => $this->checkLedgerLinks(),
                default => $this->checkWalletBalances(),
            };
        }

        return [
            'ran_at' => now()->toIso8601String(),
            'errors' => array_sum(array_column($this->report, 'errors')),
            'warnings' => array_sum(array_column($this->report, 'warnings')),
            'checks' => $this->report,
        ];
    }

    // ------------------------------------------------------------------ Level Income

    private function checkLevelIncome(): void
    {
        $payments = DB::table('payments')->where('status', 'paid')->whereIn('type', ['registration', 'emi_installment'])->orderBy('id')->get();
        $rows = DB::table('income_ledger_calculations')->where('type', 'level_income')->get()->groupBy('source_payment_id');
        $dummyFirstInstallmentPaymentIds = $this->dummyFirstInstallmentPaymentIds();

        foreach ($payments as $payment) {
            // T-149 — a dummy entry's own installment #1 is deliberately silent (never dispatches PaymentConfirmed,
            // never generates Level Income), regardless of whether the entry has since been assigned to a real leader.
            if (in_array($payment->id, $dummyFirstInstallmentPaymentIds, true)) {
                continue;
            }

            $this->tick('level');
            $stored = $rows->get($payment->id, collect());
            $ref = "payment #{$payment->id}";

            if ($stored->isEmpty()) {
                $this->error('level', "Level Income was never calculated for this paid payment (₹{$payment->amount}).", $ref);

                continue;
            }

            $payer = $this->members->get($payment->member_id);
            // T-185 — a store Repurchase on EMI uses its piece's metal.
            $metal = $this->storeEmiMetal((int) $payment->id) ?? $this->metalOf($payer);
            $rates = (array) $this->rule($stored->first()->rule_version_id, $metal === 'gold' ? 'level_income_rates_gold' : 'level_income_rates');
            $minDirects = (array) $this->rule($stored->first()->rule_version_id, 'level_income_min_directs');
            $chain = $this->sponsorChain($payment->member_id, self::MAX_LEVELS);

            if ($stored->count() !== self::MAX_LEVELS || $stored->pluck('level_no')->unique()->count() !== self::MAX_LEVELS) {
                $this->error('level', "Expected exactly one row for each of the 12 levels, found {$stored->count()}.", $ref);
            }

            for ($level = 1; $level <= self::MAX_LEVELS; $level++) {
                $row = $stored->firstWhere('level_no', $level);

                if ($row === null) {
                    $this->error('level', "Level {$level} row is missing.", $ref);

                    continue;
                }

                $beneficiary = $chain[$level - 1] ?? null;
                $rate = (float) ($rates[(string) $level] ?? 0);

                $this->compareIncomeRow('level', $row, $ref." level {$level}", $beneficiary, $rate, (float) $payment->amount, requireActive: true, requiredDirects: (int) ($minDirects[(string) $level] ?? 0));
            }
        }
    }

    // ------------------------------------------------------------------ Purchase / Repurchase

    private function checkPurchaseRepurchase(): void
    {
        // T-185c — a Repurchase on EMI handover earns nothing; it is checked for that on its own below.
        $sales = DB::table('store_sales')->where('status', 'confirmed')->whereNotNull('member_id')->whereNull('store_emi_booking_id')->orderBy('id')->get();
        $rows = DB::table('income_ledger_calculations')->where('type', 'purchase_repurchase')->get()->groupBy('source_store_sale_id');

        foreach (DB::table('store_sales')->whereNotNull('store_emi_booking_id')->pluck('id') as $saleId) {
            $this->tick('purchase');

            if ($rows->has($saleId)) {
                $this->error('purchase', 'A Repurchase on EMI handover must not generate Purchase/Repurchase income, but rows exist.', "store sale #{$saleId}");
            }
        }

        foreach ($sales as $sale) {
            $this->tick('purchase');
            $stored = $rows->get($sale->id, collect());
            $ref = "store sale #{$sale->id}";

            if ($stored->isEmpty()) {
                $this->error('purchase', 'Purchase/Repurchase income was never calculated for this confirmed sale.', $ref);

                continue;
            }

            $rates = (array) $this->rule($stored->first()->rule_version_id, $this->metalKey('purchase_repurchase_income_rates', $this->saleMetal($sale)));
            $chain = $this->sponsorChain($sale->member_id, self::MAX_LEVELS);
            // T-169 — income is on the metal value; older sales without one used their typed sale amount.
            $base = (float) ($sale->metal_value ?? $sale->sale_amount);

            $expectedCount = 1 + self::MAX_LEVELS;

            if ($stored->count() !== $expectedCount) {
                $this->error('purchase', "Expected {$expectedCount} rows (self + 12 levels), found {$stored->count()}.", $ref);
            }

            $self = $stored->first(fn ($row) => $row->level_no === null);

            if ($self === null) {
                $this->error('purchase', 'The purchaser\'s own (self) row is missing.', $ref);
            } else {
                $this->compareIncomeRow('purchase', $self, $ref.' self', $this->members->get($sale->member_id), (float) ($rates['self'] ?? 0), $base, requireActive: false);
            }

            for ($level = 1; $level <= self::MAX_LEVELS; $level++) {
                $row = $stored->firstWhere('level_no', $level);

                if ($row === null) {
                    $this->error('purchase', "Level {$level} row is missing.", $ref);

                    continue;
                }

                $beneficiary = $chain[$level - 1] ?? null;

                // T-183 — an upline level needs the beneficiary to have unlocked store income by the time of the row.
                $storeDirects = (int) ($this->rule($row->rule_version_id, 'store_income_min_directs') ?? 0);

                if ($row->skip_reason === 'store_income_locked') {
                    if ($storeDirects === 0) {
                        $this->error('purchase', "Skipped as 'store_income_locked', but this rule version has no store-income directs condition.", $ref." level {$level}");
                    } elseif ($beneficiary !== null && $this->storeIncomeUnlockedBy($beneficiary, $row->created_at)) {
                        $this->error('purchase', "Skipped as 'store_income_locked', but {$beneficiary->customer_id} had already unlocked store income.", $ref." level {$level}");
                    }

                    continue;
                }

                if ($storeDirects > 0 && $row->eligibility_status === 'paid' && $beneficiary !== null && ! $this->storeIncomeUnlockedBy($beneficiary, $row->created_at)) {
                    $this->error('purchase', "Paid to {$beneficiary->customer_id} before they unlocked store income ({$storeDirects} qualified directs).", $ref." level {$level}");
                }

                $this->compareIncomeRow('purchase', $row, $ref." level {$level}", $beneficiary, (float) ($rates[(string) $level] ?? 0), $base, requireActive: false);
            }
        }

        $this->checkWalkInIncome($rows);
    }

    /**
     * T-170 (28-09-2026) — a walk-in sale pays the whole Purchase/Repurchase percentage to the Store Owner in one
     * row. Walk-in sales recorded before T-170 have no row at all, so only sales that do have rows are checked.
     *
     * @param  Collection<array-key, Collection<int, stdClass>>  $rows
     */
    private function checkWalkInIncome(Collection $rows): void
    {
        $sales = DB::table('store_sales')
            ->join('stores', 'stores.id', '=', 'store_sales.store_id')
            ->leftJoin('members as owner', 'owner.user_id', '=', 'stores.owner_user_id')
            ->where('store_sales.status', 'confirmed')
            ->whereNull('store_sales.member_id')
            ->orderBy('store_sales.id')
            ->get(['store_sales.*', 'owner.id as owner_member_id']);

        foreach ($sales as $sale) {
            $stored = $rows->get($sale->id, collect());

            if ($stored->isEmpty()) {
                continue;
            }

            $this->tick('purchase');
            $ref = "walk-in store sale #{$sale->id}";

            if ($stored->count() !== 1) {
                $this->error('purchase', "A walk-in sale should have exactly 1 Store Owner row, found {$stored->count()}.", $ref);

                continue;
            }

            $rates = (array) $this->rule($stored->first()->rule_version_id, $this->metalKey('purchase_repurchase_income_rates', $this->saleMetal($sale)));
            $totalRate = (float) array_sum(array_map('floatval', $rates));
            $base = (float) ($sale->metal_value ?? $sale->sale_amount);
            $owner = $sale->owner_member_id !== null ? $this->members->get($sale->owner_member_id) : null;

            if ($owner === null) {
                $this->error('purchase', 'The store has no owner member, so no walk-in income should exist.', $ref);

                continue;
            }

            $this->compareIncomeRow('purchase', $stored->first(), $ref.' store owner', $owner, $totalRate, $base, requireActive: false);
        }
    }

    /**
     * Shared comparison of one `income_ledger_calculations` row against what it should be. `requireActive`: Level Income
     * skips an inactive upline (Purchase/Repurchase does not). `requiredDirects` (Level Income only, T-179): qualified
     * directs the beneficiary needed. Directs can change after the payment, so a difference from today's count is only
     * ever a warning.
     */
    private function compareIncomeRow(string $check, stdClass $row, string $ref, ?stdClass $beneficiary, float $rate, float $base, bool $requireActive, int $requiredDirects = 0): void
    {
        if ($beneficiary === null) {
            if ($row->eligibility_status !== 'skipped' || $row->skip_reason !== 'chain_too_short' || $row->beneficiary_member_id !== null || (float) $row->amount !== 0.0) {
                $this->error($check, "There is no upline at this level, so it should be a skipped 'chain_too_short' row with no beneficiary and ₹0, but it is {$row->eligibility_status} ₹{$row->amount}.", $ref);
            }

            return;
        }

        $expectedAmount = round($base * $rate / 100, 2);
        $inactiveNow = $requireActive && $beneficiary->status !== 'active';
        // T-149 — an unassigned dummy (or the seeded company root) never becomes a paid beneficiary; this is a
        // structural fact of who they are, never a "changed since" situation, so — unlike upline_inactive — it is
        // never a warning.
        // T-174 — an entry inserted under the root (`benefits_limited`) is excluded the same way.
        $isDummy = ((bool) $beneficiary->is_company_dummy && $beneficiary->dummy_status !== 'assigned')
            || (bool) ($beneficiary->benefits_limited ?? false);

        if ($row->eligibility_status === 'skipped') {
            if ($isDummy && in_array($row->skip_reason, ['upline_dummy', 'benefits_limited'], true)) {
                return;
            }

            if ($inactiveNow && $row->skip_reason === 'upline_inactive') {
                return;
            }

            if (! $inactiveNow && $row->skip_reason === 'upline_inactive') {
                $this->warn($check, "Skipped as 'upline_inactive' at the time, but {$beneficiary->customer_id} is active now (status changed since).", $ref);

                return;
            }

            // T-186 — too few directs is `held`, never skipped (the T-186 migration converted the old lapsed rows).
            $this->error($check, "Expected ₹{$expectedAmount} to {$beneficiary->customer_id} but the row is skipped ({$row->skip_reason}).", $ref);

            return;
        }

        // T-186 — held for too few directs: the amount is fixed at payment time and must be released as soon as the
        // beneficiary meets the active rule version's count for the level.
        $isHeld = $row->eligibility_status === 'held';

        if ($isHeld) {
            if ($row->skip_reason !== 'insufficient_directs' || $requiredDirects === 0) {
                $this->error($check, "Held, but only a level short of its qualified directs is held and this rule version needs {$requiredDirects} at this level.", $ref);
            } else {
                $activeRequired = (int) (((array) $this->activeRule('level_income_min_directs'))[(string) $row->level_no] ?? 0);
                $directsNow = $this->qualifiedDirectCount((int) $beneficiary->id);

                if (! $inactiveNow && $directsNow >= $activeRequired) {
                    $this->warn($check, "Held (needs {$activeRequired} qualified directs) but {$beneficiary->customer_id} has {$directsNow} now, so ₹{$row->amount} should have been released — run `php artisan level-income:release-held`.", $ref);
                }
            }
        }

        if ($isDummy) {
            $this->error($check, "{$beneficiary->customer_id} is an unassigned dummy entry, the company root or a root-inserted entry and must never be a paid beneficiary of this income, but ₹{$row->amount} was paid to them.", $ref);

            return;
        }

        if ($inactiveNow) {
            $this->warn($check, "Paid ₹{$row->amount} to {$beneficiary->customer_id}, who is not active now (status changed since).", $ref);
        }

        // A released row (T-186) was paid when the active version's count was met, so it is not compared here.
        if (! $isHeld && $row->released_at === null && $requiredDirects > 0 && ($directsNow = $this->qualifiedDirectCount((int) $beneficiary->id)) < $requiredDirects) {
            $this->warn($check, "Paid, but this level needs {$requiredDirects} qualified directs and {$beneficiary->customer_id} has {$directsNow} now (changed since, or paid under the wrong count).", $ref);
        }

        if ((int) $row->beneficiary_member_id !== (int) $beneficiary->id) {
            $this->error($check, "Should go to {$beneficiary->customer_id} (member #{$beneficiary->id}) but goes to member #{$row->beneficiary_member_id}.", $ref);
        }

        if (abs((float) $row->rate_percent - $rate) > 0.0001) {
            $this->error($check, "Rate should be {$rate}% but is {$row->rate_percent}%.", $ref);
        }

        if (! $this->same((float) $row->amount, $expectedAmount)) {
            $this->error($check, "Amount should be ₹{$expectedAmount} ({$rate}% of ₹{$base}) but is ₹{$row->amount}.", $ref);
        }
    }

    // ------------------------------------------------------------------ Store profit distribution

    private function checkStoreProfit(): void
    {
        $sales = DB::table('store_sales')->where('status', 'confirmed')->whereNull('store_emi_booking_id')->orderBy('id')->get();
        $rows = DB::table('store_profit_distributions')->get()->groupBy('store_sale_id');

        // T-185c — "store ko kuchh nahi milega": a Repurchase on EMI handover has no Store Profit rows.
        foreach (DB::table('store_sales')->whereNotNull('store_emi_booking_id')->pluck('id') as $saleId) {
            $this->tick('store');

            if ($rows->has($saleId)) {
                $this->error('store', 'A Repurchase on EMI handover must not distribute store profit, but rows exist.', "store sale #{$saleId}");
            }
        }
        $stores = DB::table('stores')->get()->keyBy('id');
        $memberByUser = $this->members->filter(fn ($m) => $m->user_id !== null)->keyBy('user_id');

        foreach ($sales as $sale) {
            $this->tick('store');
            $stored = $rows->get($sale->id, collect());
            $ref = "store sale #{$sale->id}";
            $owner = $stores->get($sale->store_id)?->owner_user_id;
            $ownerMember = $owner !== null ? $memberByUser->get($owner) : null;

            if ($ownerMember === null) {
                if ($stored->isNotEmpty()) {
                    $this->error('store', 'The store owner has no member account, so nothing should have been distributed, but rows exist.', $ref);
                }

                continue;
            }

            if ($stored->isEmpty()) {
                $this->error('store', 'Store profit was never distributed for this confirmed sale.', $ref);

                continue;
            }

            $rates = (array) $this->rule($stored->first()->rule_version_id, $this->metalKey('store_profit_distribution_rates', $this->saleMetal($sale)));
            $chain = $this->sponsorChain($ownerMember->id, 3);
            $expected = ['store_owner' => $ownerMember];

            // T-149 — an unassigned dummy (or the company root) ancestor is skipped by the Action, never credited.
            $level = 1;
            foreach ($chain as $sponsor) {
                if (($sponsor->is_company_dummy && $sponsor->dummy_status !== 'assigned') || ($sponsor->benefits_limited ?? false)) {
                    $level++;

                    continue;
                }

                // T-183 — with a directs condition, a sponsor level is paid only once the sponsor has unlocked store income.
                $type = 'sponsor_level_'.$level;
                $storeDirects = (int) ($this->rule($stored->first()->rule_version_id, 'store_income_min_directs') ?? 0);
                $row = $stored->firstWhere('beneficiary_type', $type);

                if ($storeDirects > 0 && $row === null) {
                    if ($this->storeIncomeUnlockedBy($sponsor, $sale->created_at)) {
                        $this->error('store', "{$type} ({$sponsor->customer_id}) had unlocked store income before this sale but got nothing.", $ref);
                    }

                    $level++;

                    continue;
                }

                if ($storeDirects > 0 && ! $this->storeIncomeUnlockedBy($sponsor, $row->created_at)) {
                    $this->error('store', "{$type} ({$sponsor->customer_id}) was paid before unlocking store income ({$storeDirects} qualified directs).", $ref);
                }

                $expected[$type] = $sponsor;
                $level++;
            }

            if ($stored->count() !== count($expected)) {
                $this->error('store', 'Expected '.count($expected).' beneficiaries (owner + up to 3 sponsors), found '.$stored->count().'.', $ref);
            }

            foreach ($expected as $type => $member) {
                $row = $stored->firstWhere('beneficiary_type', $type);

                if ($row === null) {
                    $this->error('store', "{$type} ({$member->customer_id}) is missing.", $ref);

                    continue;
                }

                $rate = (float) ($rates[$type] ?? 0);
                $base = (float) ($sale->metal_value ?? $sale->sale_amount);
                $amount = round($base * $rate / 100, 2);

                if ((int) $row->beneficiary_member_id !== (int) $member->id) {
                    $this->error('store', "{$type} should be {$member->customer_id} but is member #{$row->beneficiary_member_id}.", $ref);
                }

                if (! $this->same((float) $row->amount, $amount)) {
                    $this->error('store', "{$type}: ₹{$amount} expected ({$rate}% of ₹{$base}) but ₹{$row->amount} stored.", $ref);
                }
            }

            if ($sale->distribution_status !== 'processed') {
                $this->error('store', "Distribution rows exist but the sale's distribution_status is '{$sale->distribution_status}'.", $ref);
            }
        }
    }

    // ------------------------------------------------------------------ Pair entries and Pair/Reward

    private function checkPair(): void
    {
        $entries = DB::table('pair_entries')->get();
        $bySource = $entries->groupBy('source_payment_id');
        $payments = DB::table('payments')->get()->keyBy('id');

        foreach ($bySource as $paymentId => $group) {
            $this->tick('pair');
            $payment = $payments->get($paymentId);
            $ref = "payment #{$paymentId}";

            if ($payment === null || $payment->status !== 'paid') {
                $this->error('pair', 'Pair entries exist for a payment that is not paid.', $ref);

                continue;
            }

            $payer = $this->members->get($payment->member_id);
            $expected = [];

            foreach ($this->placementChain($payer->id) as [$ancestor, $side]) {
                if ($ancestor->is_company_dummy && $ancestor->dummy_status !== 'assigned') {
                    continue;
                }

                // T-174 — an entry inserted under the root after this joining was not its ancestor at the time.
                if ($this->createdAfter($ancestor, $payment->paid_at ?? $payment->created_at)) {
                    continue;
                }

                $expected[] = $ancestor->id.':'.$side;
            }

            $stored = $group->map(fn ($e) => $e->member_id.':'.$e->side)->all();
            $missing = array_diff($expected, $stored);
            $extra = array_diff($stored, $expected);

            if ($missing !== [] || $extra !== [] || count($stored) !== count(array_unique($stored))) {
                $this->error('pair', 'Entries do not match the payer\'s Binary Position ancestors. Missing: '.($this->pairList($missing)).'. Unexpected: '.($this->pairList($extra)).'.', $ref);
            }

            $metal = $this->metalOf($payer);

            if ($group->contains(fn ($e) => $e->metal !== $metal)) {
                $this->error('pair', "Entries should carry the payer's plan metal ({$metal}).", $ref);
            }
        }

        // One qualifying event per member.
        /** @var array<int, array<int, true>> $eventsPerPayer payer member id => set of source payment ids */
        $eventsPerPayer = [];

        foreach ($entries as $entry) {
            $payerId = $payments->get($entry->source_payment_id)?->member_id;

            if ($payerId !== null) {
                $eventsPerPayer[(int) $payerId][(int) $entry->source_payment_id] = true;
            }
        }

        foreach ($eventsPerPayer as $memberId => $sources) {
            $count = count($sources);

            if ($count > 1) {
                $this->error('pair', "Pair entries were created from {$count} different payments of the same member (only one joining event is allowed).", $this->memberRef($memberId));
            }
        }

        // Eligible members with no entries at all.
        $withEntries = $eventsPerPayer;

        foreach ($this->members as $member) {
            if ($member->membership_plan_id === null || isset($withEntries[$member->id]) || $this->placementChain($member->id) === []) {
                continue;
            }

            if ($this->isPairEligible($member)) {
                $this->tick('pair');
                $realAncestors = collect($this->placementChain($member->id))->filter(fn ($l) => ! ($l[0]->is_company_dummy && $l[0]->dummy_status !== 'assigned') && ! $this->createdAfter($l[0], $member->created_at));

                if ($realAncestors->isNotEmpty()) {
                    $this->error('pair', 'Eligible for pair entries (paid enough) but none were created.', $this->memberRef($member->id));
                }
            }
        }

        $this->checkPairRewards($entries);
    }

    /** @param  Collection<int, stdClass>  $entries */
    private function checkPairRewards(Collection $entries): void
    {
        $transactions = DB::table('pair_reward_transactions')->orderBy('member_id')->orderBy('milestone_no')->get();

        foreach ($transactions->groupBy('member_id') as $memberId => $list) {
            $numbers = $list->pluck('milestone_no')->all();

            if ($numbers !== range(1, count($numbers))) {
                $this->error('pair', 'Milestones were not achieved in order without gaps: '.implode(', ', $numbers).'.', $this->memberRef($memberId));
            }
        }

        foreach ($transactions as $tx) {
            $this->tick('pair');
            $ref = $this->memberRef($tx->member_id)." milestone {$tx->milestone_no}";
            $consumed = $entries->where('member_id', $tx->member_id)->where('consumed_for_milestone_no', $tx->milestone_no);
            $left = $consumed->where('side', 'left')->count();
            $right = $consumed->where('side', 'right')->count();
            $milestone = collect((array) $this->rule($tx->rule_version_id, 'pair_milestones'))->firstWhere('milestone_no', $tx->milestone_no);

            if ($milestone === null) {
                $this->error('pair', 'Milestone is not in the rule version it was calculated with.', $ref);

                continue;
            }

            if ($left !== (int) $milestone['left'] || $right !== (int) $milestone['right']) {
                $this->error('pair', "Should have consumed {$milestone['left']} Left / {$milestone['right']} Right entries, but {$left} / {$right} are marked consumed for it.", $ref);
            }

            if ((int) $tx->left_consumed_count !== (int) $milestone['left'] || (int) $tx->right_consumed_count !== (int) $milestone['right']) {
                $this->error('pair', 'The stored consumed counts differ from the milestone requirement.', $ref);
            }

            // T-178 — a warning, not an error: this compares against today's directs, and a direct can become inactive later.
            $qualifiedDirects = $this->qualifiedDirectCount((int) $tx->member_id);

            if ($qualifiedDirects < (int) $milestone['min_directs']) {
                $this->warn('pair', "Milestone needs {$milestone['min_directs']} qualified directs, but the member has {$qualifiedDirects} now.", $ref);
            }

            $silver = (float) $this->rule($tx->rule_version_id, 'pair_value_per_entry');
            $gold = (float) $this->rule($tx->rule_version_id, 'pair_value_per_entry_gold');
            $reward = round($consumed->sum(fn ($e) => $e->metal === 'gold' ? $gold : $silver), 2);

            if (! $this->same((float) $tx->reward_amount, $reward)) {
                $this->error('pair', "Reward should be ₹{$reward} ({$consumed->count()} entries at their metal's value) but ₹{$tx->reward_amount} is stored.", $ref);
            }
        }

        $known = $transactions->map(fn ($t) => $t->member_id.':'.$t->milestone_no)->flip();

        foreach ($entries->where('status', 'consumed') as $entry) {
            if (! $known->has($entry->member_id.':'.$entry->consumed_for_milestone_no)) {
                $this->error('pair', "Entry #{$entry->id} is consumed for milestone {$entry->consumed_for_milestone_no} which has no reward transaction.", $this->memberRef($entry->member_id));
            }
        }
    }

    /** T-185 — the piece's metal when this EMI payment belongs to a store Repurchase on EMI, else null. */
    private function storeEmiMetal(int $paymentId): ?string
    {
        $metal = DB::table('emi_installments as i')
            ->join('store_emi_bookings as b', 'b.emi_schedule_id', '=', 'i.emi_schedule_id')
            ->where('i.payment_id', $paymentId)
            ->value('b.metal');

        return $metal === null ? null : (string) $metal;
    }

    /** T-183 — the member had unlocked store upline income at (or before) the given moment. */
    private function storeIncomeUnlockedBy(stdClass $member, ?string $moment): bool
    {
        $unlockedAt = $member->store_income_unlocked_at ?? null;

        return $unlockedAt !== null && $moment !== null && strtotime((string) $unlockedAt) <= strtotime($moment);
    }

    private function hasOverdueEmi(int $memberId): bool
    {
        return DB::table('emi_installments as i')
            ->join('emi_schedules as s', 's.id', '=', 'i.emi_schedule_id')
            ->where('s.member_id', $memberId)
            ->where('i.status', 'overdue')
            ->exists();
    }

    private function qualifiedDirectCount(int $memberId): int
    {
        return $this->qualifiedDirectCounts[$memberId] ??= $this->members
            ->where('sponsor_id', $memberId)
            ->filter(fn (stdClass $direct): bool => $this->isPairEligible($direct))
            ->count();
    }

    private function isPairEligible(stdClass $member): bool
    {
        $plan = $this->plans->get($member->membership_plan_id);

        if ($plan === null || $member->status !== 'active') {
            return false;
        }

        if ($plan->installment_count === null) {
            return DB::table('payments')->where('member_id', $member->id)->where('type', 'registration')->where('status', 'paid')->exists();
        }

        $required = (int) (((array) $this->activeRule('pair_qualification_emis'))[$plan->code] ?? PHP_INT_MAX);
        $schedule = DB::table('emi_schedules')->where('member_id', $member->id)->where('kind', 'membership')->value('id');

        return $schedule !== null && DB::table('emi_installments')->where('emi_schedule_id', $schedule)->where('status', 'paid')->count() >= $required;
    }

    // ------------------------------------------------------------------ Booster

    private function checkBooster(): void
    {
        $schedules = DB::table('booster_payout_schedules')->get()->groupBy('booster_qualification_id');

        foreach (DB::table('booster_qualifications')->orderBy('id')->get() as $qualification) {
            $this->tick('booster');
            $ref = $this->memberRef($qualification->member_id)." booster level {$qualification->level_no}";
            $level = collect((array) $this->rule($qualification->rule_version_id, 'booster_levels'))->firstWhere('level_no', $qualification->level_no);

            if ($level === null) {
                $this->error('booster', 'The level is not in the rule version it qualified under.', $ref);

                continue;
            }

            $rows = $schedules->get($qualification->id, collect())->sortBy('month_no')->values();
            $duration = (int) $level['duration_months'];

            if ($rows->count() !== $duration) {
                $this->error('booster', "Expected {$duration} monthly payouts, found {$rows->count()}.", $ref);
            }

            foreach ($rows as $row) {
                $expectedDate = Carbon::parse($qualification->qualified_at)->startOfDay()->addMonthsNoOverflow($row->month_no - 1)->toDateString();

                if (! $this->same((float) $row->amount, (float) $level['monthly_benefit'])) {
                    $this->error('booster', "Month {$row->month_no} should be ₹{$level['monthly_benefit']} but is ₹{$row->amount}.", $ref);
                }

                if (Carbon::parse($row->scheduled_date)->toDateString() !== $expectedDate) {
                    $this->error('booster', "Month {$row->month_no} should be scheduled for {$expectedDate} but is {$row->scheduled_date}.", $ref);
                }

                if ($row->status === 'paid' && $row->wallet_ledger_entry_id === null) {
                    $this->error('booster', "Month {$row->month_no} is marked paid but has no wallet entry.", $ref);
                }

                if ($row->status === 'pending' && $row->wallet_ledger_entry_id !== null) {
                    $this->error('booster', "Month {$row->month_no} is pending but already has a wallet entry.", $ref);
                }

                if ($row->status === 'pending' && Carbon::parse($row->scheduled_date)->lt(now()->startOfDay()->subDay())) {
                    $this->warn('booster', "Month {$row->month_no} was due on {$row->scheduled_date} and is still pending (the daily job may not have run).", $ref);
                }
            }
        }
    }

    // ------------------------------------------------------------------ Wallet ledger links

    private function checkLedgerLinks(): void
    {
        $entries = DB::table('wallet_ledger_entries')->whereIn('category', ['level_income', 'purchase_repurchase_income', 'store_distribution', 'pair_reward', 'booster'])->get();
        $bySource = $entries->groupBy(fn ($e) => $e->source_type.'|'.$e->source_id);
        $seen = [];

        $sources = [
            [IncomeLedgerCalculation::class, DB::table('income_ledger_calculations')->get(), fn ($r) => $r->type === 'level_income' ? 'level_income' : 'purchase_repurchase_income', 'amount', 'beneficiary_member_id', fn ($r) => $r->eligibility_status === 'paid'],
            [StoreProfitDistribution::class, DB::table('store_profit_distributions')->get(), fn () => 'store_distribution', 'amount', 'beneficiary_member_id', fn () => true],
            [PairRewardTransaction::class, DB::table('pair_reward_transactions')->get(), fn () => 'pair_reward', 'reward_amount', 'member_id', fn () => true],
            ['App\Models\BoosterPayoutSchedule', DB::table('booster_payout_schedules as s')->join('booster_qualifications as q', 'q.id', '=', 's.booster_qualification_id')->select('s.*', 'q.member_id')->get(), fn () => 'booster', 'amount', 'member_id', fn ($r) => $r->status === 'paid'],
        ];

        foreach ($sources as [$type, $rows, $category, $amountColumn, $memberColumn, $shouldBePaid]) {
            foreach ($rows as $row) {
                $this->tick('ledger');
                $key = $type.'|'.$row->id;
                $found = $bySource->get($key, collect());
                $seen[$key] = true;
                $ref = class_basename($type)." #{$row->id}";

                if (! $shouldBePaid($row)) {
                    if ($found->isNotEmpty()) {
                        $this->error('ledger', 'This earning was not paid (skipped/pending) but a wallet entry exists.', $ref);
                    }

                    continue;
                }

                if ($found->count() !== 1) {
                    $this->error('ledger', 'Expected exactly one wallet credit for this earning, found '.$found->count().'.', $ref);

                    continue;
                }

                $entry = $found->first();

                // T-182 — a `pending` credit is an earning held while the member has an overdue EMI.
                if ($entry->entry_type !== 'credit' || ! in_array($entry->status, ['confirmed', 'pending'], true) || $entry->category !== $category($row)) {
                    $this->error('ledger', "The wallet entry should be a confirmed (or held) '{$category($row)}' credit but is {$entry->status} {$entry->entry_type} '{$entry->category}'.", $ref);
                } elseif ($entry->status === 'pending' && ! $this->hasOverdueEmi((int) $entry->member_id)) {
                    $this->warn('ledger', "₹{$entry->amount} is still held, but member #{$entry->member_id} has no overdue EMI now, so it should have been released.", $ref);
                }

                if (! $this->same((float) $entry->amount, (float) $row->{$amountColumn}) || (int) $entry->member_id !== (int) $row->{$memberColumn}) {
                    $this->error('ledger', "The wallet entry (₹{$entry->amount} to member #{$entry->member_id}) differs from the earning (₹{$row->{$amountColumn}} to member #{$row->{$memberColumn}}).", $ref);
                }
            }
        }

        foreach ($entries as $entry) {
            if (! isset($seen[$entry->source_type.'|'.$entry->source_id])) {
                $this->tick('ledger');
                $this->error('ledger', "A '{$entry->category}' wallet entry of ₹{$entry->amount} points to a source that does not exist.", "wallet entry #{$entry->id}");
            }
        }
    }

    // ------------------------------------------------------------------ Wallet balances

    private function checkWalletBalances(): void
    {
        $sums = DB::table('wallet_ledger_entries')
            ->selectRaw("member_id, sum(case when entry_type = 'credit' and status = 'confirmed' then amount else 0 end) as credits, sum(case when entry_type = 'debit' and status = 'confirmed' then amount else 0 end) as debits, sum(case when entry_type = 'debit' and status = 'pending' then amount else 0 end) as holds")
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        foreach ($this->members as $member) {
            $this->tick('wallet');
            $sum = $sums->get($member->id);
            $balance = round((float) ($sum->credits ?? 0) - (float) ($sum->debits ?? 0), 2);
            $hold = round((float) ($sum->holds ?? 0), 2);

            if (! $this->same((float) $member->wallet_balance, $balance)) {
                $this->error('wallet', "Wallet balance is ₹{$member->wallet_balance} but the ledger adds up to ₹{$balance}.", $this->memberRef($member->id));
            }

            if (! $this->same((float) $member->wallet_hold_amount, $hold)) {
                $this->error('wallet', "Amount on hold is ₹{$member->wallet_hold_amount} but pending payout holds add up to ₹{$hold}.", $this->memberRef($member->id));
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    /**
     * T-174 — normally every Binary Position ancestor is older than its descendants; only an entry inserted directly
     * under the root (System Maintenance) sits above members that joined before it.
     */
    private function createdAfter(stdClass $ancestor, ?string $moment): bool
    {
        if ($moment === null || $ancestor->created_at === null) {
            return false;
        }

        return strtotime((string) $ancestor->created_at) > strtotime($moment);
    }

    /** @return list<stdClass> Sponsor/Direct ancestors, level 1 first. */
    private function sponsorChain(int $memberId, int $max): array
    {
        $chain = [];
        $current = $this->members->get($memberId);

        for ($i = 0; $i < $max && $current !== null && $current->sponsor_id !== null; $i++) {
            $current = $this->members->get($current->sponsor_id);

            if ($current !== null) {
                $chain[] = $current;
            }
        }

        return $chain;
    }

    /** @return list<array{0: stdClass, 1: string}> Binary Position ancestors with the leg the member sits on under each. */
    private function placementChain(int $memberId): array
    {
        $chain = [];
        $current = $this->members->get($memberId);

        for ($i = 0; $i < 2000 && $current !== null && $current->placement_parent_id !== null; $i++) {
            $parent = $this->members->get($current->placement_parent_id);

            if ($parent === null) {
                break;
            }

            $chain[] = [$parent, (string) $current->placement_side];
            $current = $parent;
        }

        return $chain;
    }

    /**
     * T-149 — the `payments.id` of every dummy entry's silent installment #1 (member currently or formerly
     * `is_company_dummy`), which deliberately has no Level Income rows regardless of the entry's current assignment
     * status. Small table in practice (one row per dummy entry ever generated).
     *
     * @return list<int>
     */
    private function dummyFirstInstallmentPaymentIds(): array
    {
        return array_values(DB::table('emi_installments as i')
            ->join('emi_schedules as s', 's.id', '=', 'i.emi_schedule_id')
            ->join('members as m', 'm.id', '=', 's.member_id')
            ->where('m.is_company_dummy', true)
            ->where('i.installment_no', 1)
            ->whereNotNull('i.payment_id')
            ->pluck('i.payment_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    private function metalOf(?stdClass $member): string
    {
        return (string) ($this->plans->get($member?->membership_plan_id)->product_category ?? 'silver');
    }

    private function saleMetal(stdClass $sale): string
    {
        return (string) ($sale->metal ?? 'silver');
    }

    private function metalKey(string $base, string $metal): string
    {
        return $metal === 'gold' ? "{$base}_gold" : $base;
    }

    private function rule(int $versionId, string $key): mixed
    {
        $cacheKey = "{$versionId}:{$key}";

        if (! array_key_exists($cacheKey, $this->ruleCache)) {
            $this->ruleCache[$cacheKey] = RuleValue::where('rule_version_id', $versionId)->where('key', $key)->first()?->value;
        }

        return $this->ruleCache[$cacheKey];
    }

    private function activeRule(string $key): mixed
    {
        $versionId = DB::table('rule_versions')->where('is_active', true)->value('id');

        return $versionId !== null ? $this->rule((int) $versionId, $key) : null;
    }

    /** @param  array<int, string>  $pairs */
    private function pairList(array $pairs): string
    {
        if ($pairs === []) {
            return 'none';
        }

        return collect($pairs)->map(function (string $pair) {
            [$id, $side] = explode(':', $pair);

            return ($this->members->get((int) $id)->customer_id ?? "#{$id}").' '.$side;
        })->implode(', ');
    }

    private function memberRef(int|string|null $memberId): string
    {
        $member = $memberId !== null ? $this->members->get((int) $memberId) : null;

        return $member ? "member {$member->customer_id}" : "member #{$memberId}";
    }

    private function same(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }

    private function tick(string $check): void
    {
        $this->report[$check]['checked']++;
    }

    private function error(string $check, string $message, string $ref): void
    {
        $this->add($check, 'error', $message, $ref);
    }

    private function warn(string $check, string $message, string $ref): void
    {
        $this->add($check, 'warning', $message, $ref);
    }

    private function add(string $check, string $severity, string $message, string $ref): void
    {
        $entry = $this->report[$check];

        if ($severity === 'error') {
            $entry['errors']++;
        } else {
            $entry['warnings']++;
        }

        if (count($entry['findings']) < $this->limit) {
            $entry['findings'][] = ['severity' => $severity, 'message' => $message, 'ref' => $ref];
        }

        $this->report[$check] = $entry;
    }
}
