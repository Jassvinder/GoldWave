<?php

namespace App\Actions\Compensation;

use App\Models\Member;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Services\RuleVersionService;
use App\Services\SponsorChainResolver;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §16.4 — fires on every confirmed store sale (walk-in,
 * member purchase, or plan-jewellery delivery alike, §16.4's 3 resolved
 * scenarios), never on a Buyback (§16.9). Store Owner 2%, then their own
 * Sponsor/Direct chain Levels 1-3 at 0.5%/0.25%/0.25%, on the full
 * distributable sale amount. §21/§17.3 point 4: the Store Owner is always
 * also a full network Member — resolved via `Store::ownerMember()` — so if
 * that resolution fails (no owner assigned, or the owner has no Member row),
 * the whole distribution is skipped, since there is no chain to walk without
 * a starting point.
 *
 * Idempotent via the `store_sale_id` existence check, the same pattern
 * `CalculatePurchaseRepurchaseIncome` uses.
 */
class CalculateStoreProfitDistribution
{
    private const SPONSOR_LEVELS = 3;

    public function __construct(
        private readonly SponsorChainResolver $sponsorChain,
        private readonly RuleVersionService $rules,
        private readonly WalletLedgerService $wallet,
    ) {}

    public function __invoke(StoreSale $storeSale): void
    {
        if (StoreProfitDistribution::where('store_sale_id', $storeSale->id)->exists()) {
            return;
        }

        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            return;
        }

        $store = $storeSale->store()->firstOrFail();
        $ownerMember = $store->ownerMember();

        if (! $ownerMember) {
            return;
        }

        $rates = $this->rules->value('store_profit_distribution_rates', []);
        $baseAmount = (float) $storeSale->sale_amount;
        $chain = $this->sponsorChain->ancestors($ownerMember, self::SPONSOR_LEVELS);

        DB::transaction(function () use ($storeSale, $ownerMember, $ruleVersion, $rates, $baseAmount, $chain) {
            $this->payBeneficiary($storeSale, 'store_owner', $ownerMember, (float) ($rates['store_owner'] ?? 0), $baseAmount, $ruleVersion->id);

            for ($level = 1; $level <= self::SPONSOR_LEVELS; $level++) {
                $beneficiary = $chain[$level - 1] ?? null;

                if (! $beneficiary) {
                    continue;
                }

                $this->payBeneficiary(
                    $storeSale, "sponsor_level_{$level}", $beneficiary,
                    (float) ($rates["sponsor_level_{$level}"] ?? 0), $baseAmount, $ruleVersion->id,
                );
            }

            $storeSale->update(['distribution_status' => 'processed']);
        });
    }

    private function payBeneficiary(
        StoreSale $storeSale,
        string $beneficiaryType,
        Member $beneficiary,
        float $rate,
        float $baseAmount,
        int $ruleVersionId,
    ): void {
        $amount = round($baseAmount * $rate / 100, 2);

        $distribution = StoreProfitDistribution::create([
            'store_sale_id' => $storeSale->id,
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_member_id' => $beneficiary->id,
            'rate_percent' => $rate,
            'amount' => $amount,
            'rule_version_id' => $ruleVersionId,
        ]);

        $this->wallet->credit(
            $beneficiary,
            'store_distribution',
            $amount,
            $distribution,
            "Store Profit Distribution ({$beneficiaryType}) from store sale #{$storeSale->id}",
        );
    }
}
