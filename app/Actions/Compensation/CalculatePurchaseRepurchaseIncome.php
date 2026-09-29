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
 * DOMAIN_LOGIC.md §15 — for a sale attributed to a purchasing member
 * (`store_sales.member_id` set); since T-170 (28-09-2026) a walk-in sale pays
 * the whole percentage to the Store Owner instead (see
 * `payWalkInIncomeToStoreOwner`). Pays the purchasing member 2% ("self"), then walks
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
        if (IncomeLedgerCalculation::where('source_store_sale_id', $storeSale->id)
            ->where('type', 'purchase_repurchase')
            ->exists()) {
            return;
        }

        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            return;
        }

        if (! $storeSale->member_id) {
            $this->payWalkInIncomeToStoreOwner($storeSale, $ruleVersion);

            return;
        }

        $payer = $storeSale->member()->firstOrFail();
        $metal = $storeSale->metal;
        $rates = $this->rules->metalValue('purchase_repurchase_income_rates', $metal, []);
        $baseAmount = $storeSale->incomeBase();
        $chain = $this->sponsorChain->ancestors($payer, self::MAX_LEVELS);

        DB::transaction(function () use ($storeSale, $payer, $ruleVersion, $rates, $baseAmount, $chain) {
            $selfRate = (float) ($rates['self'] ?? 0);
            $this->payBeneficiary($storeSale, $payer, $ruleVersion, null, $selfRate, $baseAmount, "Self Purchase/Repurchase Income on {$payer->customer_id}'s own purchase");

            for ($level = 1; $level <= self::MAX_LEVELS; $level++) {
                $beneficiary = $chain[$level - 1] ?? null;
                $rate = (float) ($rates[(string) $level] ?? 0);

                if (! $beneficiary) {
                    $this->recordSkipped($storeSale, $ruleVersion, $level, $rate, null, 'chain_too_short');

                    continue;
                }

                // T-149 — an unassigned dummy (or the seeded company root, never assignable) must never itself become a
                // paid compensation beneficiary, matching the same guard already applied to Pair entries/Booster.
                if ($beneficiary->is_company_dummy && $beneficiary->dummy_status !== 'assigned') {
                    $this->recordSkipped($storeSale, $ruleVersion, $level, $rate, $beneficiary, 'upline_dummy');

                    continue;
                }

                $this->payBeneficiary(
                    $storeSale, $beneficiary, $ruleVersion, $level, $rate, $baseAmount,
                    "Level {$level} Purchase/Repurchase Income from {$payer->customer_id}'s purchase",
                );
            }
        });
    }

    /**
     * T-170 (28-09-2026, user decision — DOMAIN_LOGIC.md §15 note, TEST.md scenario 5): a walk-in buyer has no
     * chain, so the whole Purchase/Repurchase percentage (self + every level of this metal's rates) goes to the
     * Store Owner's member wallet as one entry, described as store income. No owner member → nothing (as for
     * Store Profit Distribution).
     */
    private function payWalkInIncomeToStoreOwner(StoreSale $storeSale, RuleVersion $ruleVersion): void
    {
        $store = $storeSale->store()->first();
        $owner = $store?->ownerMember();

        if ($store === null || $owner === null) {
            return;
        }

        $rates = $this->rules->metalValue('purchase_repurchase_income_rates', $storeSale->metal, []);
        $totalRate = (float) array_sum(array_map('floatval', (array) $rates));

        DB::transaction(fn () => $this->payBeneficiary(
            $storeSale, $owner, $ruleVersion, null, $totalRate, $storeSale->incomeBase(),
            "Walk-in store sale income — {$store->name} sale #{$storeSale->id}",
        ));
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

    private function recordSkipped(StoreSale $storeSale, RuleVersion $ruleVersion, int $level, float $rate, ?Member $beneficiary, string $reason): void
    {
        IncomeLedgerCalculation::create([
            'type' => 'purchase_repurchase',
            'source_store_sale_id' => $storeSale->id,
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
