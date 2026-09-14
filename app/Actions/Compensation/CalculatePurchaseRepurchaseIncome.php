<?php

namespace App\Actions\Compensation;

use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\RuleVersion;
use App\Models\StoreSale;
use App\Services\RuleVersionService;
use App\Services\SponsorChainResolver;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §15 — fires only when a store sale is attributed to a
 * purchasing member (`store_sales.member_id` set); a no-op for a walk-in/
 * non-member sale (`DOMAIN_LOGIC.md` §16.4 scenario 1, Docs/TEST.md scenario
 * 5's regression case). Pays the purchasing member 2% ("self"), then walks
 * their Sponsor/Direct chain: Level 1 (direct Sponsor) 1%, Levels 2-6 0.5%
 * each, Levels 7-12 0.25% each — a strict ancestor path, so the
 * duplicate-beneficiary rule (§15) is structurally satisfied without extra
 * code (Docs/TEST.md scenario 4's regression note).
 *
 * Idempotent via the (type, source_store_sale_id) existence check, the same
 * pattern `CalculateLevelIncome` uses for `source_payment_id`.
 */
class CalculatePurchaseRepurchaseIncome
{
    private const MAX_LEVELS = 12;

    public function __construct(
        private readonly SponsorChainResolver $sponsorChain,
        private readonly RuleVersionService $rules,
        private readonly WalletLedgerService $wallet,
    ) {}

    public function __invoke(StoreSale $storeSale): void
    {
        if (! $storeSale->member_id) {
            return;
        }

        if (IncomeLedgerCalculation::where('source_store_sale_id', $storeSale->id)
            ->where('type', 'purchase_repurchase')
            ->exists()) {
            return;
        }

        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            return;
        }

        $payer = $storeSale->member()->firstOrFail();
        $rates = $this->rules->value('purchase_repurchase_income_rates', []);
        $baseAmount = (float) $storeSale->sale_amount;
        $chain = $this->sponsorChain->ancestors($payer, self::MAX_LEVELS);

        DB::transaction(function () use ($storeSale, $payer, $ruleVersion, $rates, $baseAmount, $chain) {
            $selfRate = (float) ($rates['self'] ?? 0);
            $this->payBeneficiary($storeSale, $payer, $ruleVersion, null, $selfRate, $baseAmount, "Self Purchase/Repurchase Income on {$payer->customer_id}'s own purchase");

            for ($level = 1; $level <= self::MAX_LEVELS; $level++) {
                $beneficiary = $chain[$level - 1] ?? null;
                $rate = (float) ($rates[(string) $level] ?? 0);

                if (! $beneficiary) {
                    IncomeLedgerCalculation::create([
                        'type' => 'purchase_repurchase',
                        'source_store_sale_id' => $storeSale->id,
                        'beneficiary_member_id' => null,
                        'level_no' => $level,
                        'rate_percent' => $rate,
                        'amount' => 0,
                        'rule_version_id' => $ruleVersion->id,
                        'eligibility_status' => 'skipped',
                        'skip_reason' => 'chain_too_short',
                    ]);

                    continue;
                }

                $this->payBeneficiary(
                    $storeSale, $beneficiary, $ruleVersion, $level, $rate, $baseAmount,
                    "Level {$level} Purchase/Repurchase Income from {$payer->customer_id}'s purchase",
                );
            }
        });
    }

    private function payBeneficiary(
        StoreSale $storeSale,
        Member $beneficiary,
        RuleVersion $ruleVersion,
        ?int $levelNo,
        float $rate,
        float $baseAmount,
        string $description,
    ): void {
        $amount = round($baseAmount * $rate / 100, 2);

        $calculation = IncomeLedgerCalculation::create([
            'type' => 'purchase_repurchase',
            'source_store_sale_id' => $storeSale->id,
            'beneficiary_member_id' => $beneficiary->id,
            'level_no' => $levelNo,
            'rate_percent' => $rate,
            'amount' => $amount,
            'rule_version_id' => $ruleVersion->id,
            'eligibility_status' => 'paid',
        ]);

        $this->wallet->credit($beneficiary, 'purchase_repurchase_income', $amount, $calculation, $description);
    }
}
