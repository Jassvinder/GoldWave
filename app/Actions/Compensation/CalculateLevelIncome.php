<?php

namespace App\Actions\Compensation;

use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\Payment;
use App\Models\RuleVersion;
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
 * Docs/TEST.md scenario 1's edge case).
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
        $rates = $this->rules->value('level_income_rates', []);
        $chain = $this->sponsorChain->ancestors($payer, self::MAX_LEVELS);

        DB::transaction(function () use ($payment, $payer, $ruleVersion, $rates, $chain) {
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

                $amount = round(((float) $payment->amount) * $rate / 100, 2);

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

                $this->wallet->credit(
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
