<?php

namespace App\Actions\Compensation;

use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Services\PairQualifiedDirects;
use App\Services\RuleVersionService;
use App\Services\WalletLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * T-186 (DOMAIN_LOGIC.md §6, Docs/TEST.md scenario 36) — Level Income that was `held` because the beneficiary had too
 * few qualified directs is paid as soon as they have the level's number under the **active** rule version's
 * `level_income_min_directs`. No time limit. Each level is checked on its own, so 2 directs release every held L1 row
 * while L2 (needs 4) stays held. The beneficiary must be `active` at release; otherwise the row stays held. The
 * amount is the one fixed at payment time. The credit goes through `creditEarning()`, so an overdue EMI (T-182) still
 * holds it in the wallet.
 *
 * Triggered when a direct qualifies (`ReleaseHeldLevelIncomeOnPaymentConfirmed`), when a rule version is published
 * (`PublishRuleVersion`), and on demand by `php artisan level-income:release-held`. Idempotent: a row is released
 * once, under a row lock.
 */
class ReleaseHeldLevelIncome
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly PairQualifiedDirects $qualifiedDirects,
        private readonly WalletLedgerService $wallet,
    ) {}

    /** Returns the total amount released for this member. */
    public function __invoke(Member $member): float
    {
        if ($member->status !== 'active' || $member->isExcludedFromGeneralIncome()) {
            return 0.0;
        }

        $minDirects = (array) $this->rules->value('level_income_min_directs', []);

        return DB::transaction(function () use ($member, $minDirects) {
            $held = IncomeLedgerCalculation::where('beneficiary_member_id', $member->id)
                ->where('type', 'level_income')
                ->where('eligibility_status', 'held')
                ->with('sourcePayment.member')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($held->isEmpty()) {
                return 0.0;
            }

            $directs = $this->qualifiedDirects->count($member);
            $released = 0.0;

            foreach ($held as $row) {
                if ($directs < (int) ($minDirects[(string) $row->level_no] ?? 0)) {
                    continue;
                }

                $row->update(['eligibility_status' => 'paid', 'skip_reason' => null, 'released_at' => now()]);

                $payer = $row->sourcePayment?->member?->customer_id;
                $this->wallet->creditEarning(
                    $member,
                    'level_income',
                    (float) $row->amount,
                    $row,
                    "Level {$row->level_no} income from {$payer}'s payment #{$row->source_payment_id} (held until directs were met)",
                );

                $released += (float) $row->amount;
            }

            return round($released, 2);
        });
    }

    /**
     * Every member with held Level Income — used after a rule version change and by the release command.
     *
     * @return array<string, float> released amount by Customer ID (members with nothing released are left out)
     */
    public function forAll(): array
    {
        $memberIds = IncomeLedgerCalculation::where('type', 'level_income')
            ->where('eligibility_status', 'held')
            ->distinct()
            ->pluck('beneficiary_member_id');

        $released = [];

        foreach (Member::whereIn('id', $memberIds)->orderBy('id')->get() as $member) {
            $amount = ($this)($member);

            if ($amount > 0) {
                $released[$member->customer_id] = $amount;
            }
        }

        return $released;
    }
}
