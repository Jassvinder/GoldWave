<?php

namespace App\Actions\Compensation;

use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\Payment;
use App\Models\RuleVersion;
use App\Services\PairQualifiedDirects;
use App\Services\RuleVersionService;
use App\Services\SponsorChainResolver;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §6 — one `income_ledger_calculations` row (type=
 * level_income) per Sponsor/Direct level 1-12, credited to the resolved
 * beneficiary's wallet. A level with no eligible beneficiary (chain shorter
 * than 12, or a resolved beneficiary who isn't currently 'active') gets a
 * `skipped` row instead of being silently omitted (§6.1 point 9,
 * Docs/TEST.md scenario 1's edge case). A beneficiary short of a level's
 * qualified directs gets a `held` row instead (T-186), paid later by
 * `ReleaseHeldLevelIncome`.
 *
 * "Upline inactive" (§6.1 point 9) is resolved here as `members.status !==
 * 'active'` — reusing the same Active/not-Active definition DOMAIN_LOGIC.md
 * §21 already settled for sponsor-inactive-at-registration, not a new rule
 * needing separate client confirmation.
 *
 * Idempotent: the (source_payment_id, level_no) DB unique constraint means a
 * retried/duplicate `PaymentConfirmed` dispatch can never double-create a
 * level's row; the upfront `exists()` check keeps a retry a clean no-op
 * rather than a caught constraint-violation exception.
 */
class CalculateLevelIncome
{
    private const MAX_LEVELS = 12;

    public function __construct(
        private readonly SponsorChainResolver $sponsorChain,
        private readonly RuleVersionService $rules,
        private readonly WalletLedgerService $wallet,
        private readonly PairQualifiedDirects $qualifiedDirects,
    ) {}

    public function __invoke(Payment $payment): void
    {
        if (IncomeLedgerCalculation::where('source_payment_id', $payment->id)
            ->where('type', 'level_income')
            ->exists()) {
            return;
        }

        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            return;
        }

        $payer = $payment->member()->firstOrFail();
        // T-185 — a store Repurchase on EMI pays Level Income at the rates of the piece's metal, not the plan's.
        $metal = $payment->storeEmiBooking()->metal ?? $payer->membershipPlan->product_category;
        $rates = $this->rules->metalValue('level_income_rates', $metal, []);
        $chain = $this->sponsorChain->ancestors($payer, self::MAX_LEVELS);
        $minDirects = (array) $this->rules->value('level_income_min_directs', []);

        DB::transaction(function () use ($payment, $payer, $ruleVersion, $rates, $chain, $minDirects) {
            for ($level = 1; $level <= self::MAX_LEVELS; $level++) {
                $beneficiary = $chain[$level - 1] ?? null;
                $rate = (float) ($rates[(string) $level] ?? 0);

                if (! $beneficiary) {
                    $this->recordSkipped($payment, $ruleVersion, $level, $rate, null, 'chain_too_short');

                    continue;
                }

                if ($beneficiary->status !== 'active') {
                    $this->recordSkipped($payment, $ruleVersion, $level, $rate, $beneficiary, 'upline_inactive');

                    continue;
                }

                // T-149 — an unassigned dummy (or the seeded company root, never assignable) must never itself become a
                // paid compensation beneficiary, matching the same guard already applied to Pair entries/Booster.
                // T-174 — nor does an entry inserted under the root (Pair/Reward and Booster only).
                if ($beneficiary->isExcludedFromGeneralIncome()) {
                    $this->recordSkipped($payment, $ruleVersion, $level, $rate, $beneficiary, $beneficiary->generalIncomeSkipReason());

                    continue;
                }

                $amount = round(((float) $payment->amount) * $rate / 100, 2);

                // T-179 — each level needs its total of qualified directs (default 2 × level no.). T-186 — short of
                // them the income is `held` with its amount (no wallet entry) and `ReleaseHeldLevelIncome` pays it
                // once the directs are met (DOMAIN_LOGIC.md §6, Docs/TEST.md scenario 36).
                $requiredDirects = (int) ($minDirects[(string) $level] ?? 0);

                if ($requiredDirects > 0 && $this->qualifiedDirects->count($beneficiary) < $requiredDirects) {
                    IncomeLedgerCalculation::create([
                        'type' => 'level_income',
                        'source_payment_id' => $payment->id,
                        'beneficiary_member_id' => $beneficiary->id,
                        'level_no' => $level,
                        'rate_percent' => $rate,
                        'amount' => $amount,
                        'rule_version_id' => $ruleVersion->id,
                        'eligibility_status' => 'held',
                        'skip_reason' => 'insufficient_directs',
                    ]);

                    continue;
                }

                $calculation = IncomeLedgerCalculation::create([
                    'type' => 'level_income',
                    'source_payment_id' => $payment->id,
                    'beneficiary_member_id' => $beneficiary->id,
                    'level_no' => $level,
                    'rate_percent' => $rate,
                    'amount' => $amount,
                    'rule_version_id' => $ruleVersion->id,
                    'eligibility_status' => 'paid',
                ]);

                $this->wallet->creditEarning(
                    $beneficiary,
                    'level_income',
                    $amount,
                    $calculation,
                    "Level {$level} income from {$payer->customer_id}'s payment #{$payment->id}",
                );
            }
        });
    }

    private function recordSkipped(
        Payment $payment,
        RuleVersion $ruleVersion,
        int $level,
        float $rate,
        ?Member $beneficiary,
        string $reason,
    ): void {
        IncomeLedgerCalculation::create([
            'type' => 'level_income',
            'source_payment_id' => $payment->id,
            'beneficiary_member_id' => $beneficiary?->id,
            'level_no' => $level,
            'rate_percent' => $rate,
            'amount' => 0,
            'rule_version_id' => $ruleVersion->id,
            'eligibility_status' => 'skipped',
            'skip_reason' => $reason,
        ]);
    }
}
