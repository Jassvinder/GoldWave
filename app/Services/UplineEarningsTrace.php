<?php

namespace App\Services;

use App\Models\DrawExecution;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\PairEntry;
use App\Models\Payment;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Models\WalletLedgerEntry;
use App\Support\Dates;
use Illuminate\Support\Collection;

/**
 * T-238 (DOMAIN_LOGIC.md §12.3) — every earning that went to someone else because of one member's own activity:
 * Level Income on their payments (§6), Purchase/Repurchase income on their store purchases (§15, including their
 * own "self" share), Store Profit Distribution on those purchases (§16.4), the Sponsor's draw benefit when they won
 * (§8.5), and the Pair entries their joining created in each Binary Position upline's leg (§7). Income Booster is
 * left out: it is never triggered by one downline's payment. Read-only.
 *
 * Status per row: `paid`; `held` (an `income_ledger_calculations` row held for directs, T-186, or a paid row whose
 * wallet credit is still pending for an overdue EMI, T-182); `skipped` (with its reason); and for Pair entries
 * `pair_used` / `pair_unused`.
 */
class UplineEarningsTrace
{
    public const STATUSES = ['paid', 'held', 'skipped', 'pair_used', 'pair_unused'];

    public const TYPES = ['level_income', 'purchase_repurchase', 'store_profit', 'draw_benefit', 'pair_entry'];

    private const SKIP_REASONS = [
        'chain_too_short' => 'No upline at this level',
        'upline_inactive' => 'Upline not active',
        'member_blocked' => 'Account blocked',
        'benefits_limited' => 'Entry under root — Pair/Booster only',
        'upline_dummy' => 'Unassigned dummy entry',
        'insufficient_directs' => 'Waiting for qualified directs',
        'store_income_locked' => 'Store income not unlocked (10 qualified directs)',
    ];

    public function __construct(private readonly SponsorChainResolver $sponsorChain) {}

    /**
     * Newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Member $member): Collection
    {
        $paymentIds = Payment::where('member_id', $member->id)->pluck('id');
        $saleIds = StoreSale::where('member_id', $member->id)->pluck('id');

        return collect()
            ->concat($this->incomeRows($paymentIds, $saleIds))
            ->concat($this->storeProfitRows($saleIds))
            ->concat($this->drawRows($member))
            ->concat($this->pairRows($paymentIds))
            ->sortBy([['date', 'desc'], ['key', 'desc']])
            ->values();
    }

    /**
     * One line per member who received something, ordered by their level in this member's Sponsor/Direct chain
     * (self first, then levels 1-12, then anyone outside the chain — a store owner's chain or a Binary Position upline).
     * A deep binary tree can give hundreds of uplines nothing but one Pair entry; those are only counted
     * (`pair_only_count`) and stay listed in the rows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{receivers: array<int, array<string, mixed>>, pair_only_count: int}
     */
    public function summary(Member $member, Collection $rows): array
    {
        $levels = [$member->id => 0];

        foreach ($this->sponsorChain->ancestors($member, 12) as $index => $ancestor) {
            $levels[$ancestor->id] = $index + 1;
        }

        [$receivers, $pairOnly] = $rows
            ->whereNotNull('beneficiary_id')
            ->groupBy('beneficiary_id')
            ->map(function (Collection $group, int|string $beneficiaryId) use ($levels): array {
                $first = $group->first();
                $money = $group->whereIn('type', ['level_income', 'purchase_repurchase', 'store_profit']);
                $sum = fn (Collection $items): string => $this->money((float) $items->sum(fn (array $row): float => (float) $row['amount']));

                return [
                    'beneficiary_id' => (int) $beneficiaryId,
                    'customer_id' => $first['beneficiary_customer_id'],
                    'name' => $first['beneficiary_name'],
                    'sponsor_level' => $levels[(int) $beneficiaryId] ?? null,
                    'paid' => $sum($money->where('status', 'paid')),
                    'held' => $sum($money->where('status', 'held')),
                    'skipped_count' => $group->where('status', 'skipped')->count(),
                    'prizes' => $group->where('type', 'draw_benefit')->count(),
                    'pair_entries' => $group->where('type', 'pair_entry')->count(),
                    'pair_used' => $group->where('status', 'pair_used')->count(),
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => ($a['sponsor_level'] ?? PHP_INT_MAX) <=> ($b['sponsor_level'] ?? PHP_INT_MAX),
                ['customer_id', 'asc'],
            ])
            ->partition(fn (array $receiver): bool => $receiver['sponsor_level'] !== null
                || (float) $receiver['paid'] > 0 || (float) $receiver['held'] > 0
                || $receiver['skipped_count'] > 0 || $receiver['prizes'] > 0);

        return ['receivers' => $receivers->values()->all(), 'pair_only_count' => $pairOnly->count()];
    }

    /**
     * @param  Collection<int, int>  $paymentIds
     * @param  Collection<int, int>  $saleIds
     * @return array<int, array<string, mixed>>
     */
    private function incomeRows(Collection $paymentIds, Collection $saleIds): array
    {
        $calculations = IncomeLedgerCalculation::with(['beneficiary.user', 'sourcePayment'])
            ->where(fn ($q) => $q->whereIn('source_payment_id', $paymentIds)->orWhereIn('source_store_sale_id', $saleIds))
            ->get();
        $sales = StoreSale::with('store')->whereIn('id', $calculations->pluck('source_store_sale_id')->filter()->unique())->get()->keyBy('id');
        $pending = $this->pendingCreditSources(new IncomeLedgerCalculation, $calculations->pluck('id'));

        return $calculations->map(function (IncomeLedgerCalculation $calc) use ($sales, $pending): array {
            [$status, $reason] = match (true) {
                $calc->eligibility_status === 'skipped' => ['skipped', $this->reasonLabel($calc->skip_reason)],
                $calc->eligibility_status === 'held' => ['held', $this->reasonLabel($calc->skip_reason)],
                $pending->contains($calc->id) => ['held', 'Overdue EMI — credited once the EMI is paid'],
                default => ['paid', $calc->released_at !== null ? 'Released after directs were met' : null],
            };
            $sale = $calc->source_store_sale_id !== null ? $sales->get($calc->source_store_sale_id) : null;

            return [
                'key' => "income-{$calc->id}",
                'date' => Dates::date($calc->created_at),
                'type' => $calc->type,
                ...$this->beneficiary($calc->beneficiary),
                'relation' => $calc->level_no === null ? 'Self' : "Level {$calc->level_no}",
                'source' => $sale !== null ? $this->saleLabel($sale) : $this->paymentLabel($calc->sourcePayment),
                'base_amount' => $sale !== null ? $this->money($sale->incomeBase()) : $calc->sourcePayment?->amount,
                'rate_percent' => $calc->rate_percent,
                'amount' => $calc->amount,
                'status' => $status,
                'reason' => $reason,
            ];
        })->all();
    }

    /**
     * §16.4 — the share goes to the store's owner and the owner's own chain, not the buyer's; the relation says so.
     *
     * @param  Collection<int, int>  $saleIds
     * @return array<int, array<string, mixed>>
     */
    private function storeProfitRows(Collection $saleIds): array
    {
        $distributions = StoreProfitDistribution::with(['beneficiary.user', 'storeSale.store'])->whereIn('store_sale_id', $saleIds)->get();
        $pending = $this->pendingCreditSources(new StoreProfitDistribution, $distributions->pluck('id'));

        return $distributions->map(fn (StoreProfitDistribution $distribution): array => [
            'key' => "store-{$distribution->id}",
            'date' => Dates::date($distribution->created_at),
            'type' => 'store_profit',
            ...$this->beneficiary($distribution->beneficiary),
            'relation' => $distribution->beneficiary_type === 'store_owner'
                ? 'Store owner'
                : 'Store owner\'s sponsor L'.substr($distribution->beneficiary_type, -1),
            'source' => $this->saleLabel($distribution->storeSale),
            'base_amount' => $this->money($distribution->storeSale->incomeBase()),
            'rate_percent' => $distribution->rate_percent,
            'amount' => $distribution->amount,
            'status' => $pending->contains($distribution->id) ? 'held' : 'paid',
            'reason' => $pending->contains($distribution->id) ? 'Overdue EMI — credited once the EMI is paid' : null,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function drawRows(Member $member): array
    {
        return DrawExecution::with(['drawGroup.monthConfigs', 'uplineBenefitMember.user'])
            ->where('winner_member_id', $member->id)
            ->whereNotNull('upline_benefit_member_id')
            ->get()
            ->map(function (DrawExecution $execution): array {
                $prize = $execution->drawGroup->monthConfigs->firstWhere('cycle_month_no', $execution->cycle_month_no);

                return [
                    'key' => "draw-{$execution->id}",
                    'date' => Dates::date($execution->executed_at),
                    'type' => 'draw_benefit',
                    ...$this->beneficiary($execution->uplineBenefitMember),
                    'relation' => 'Sponsor (10+ directs)',
                    'source' => "Draw win · Group {$execution->drawGroup->group_no}, month {$execution->cycle_month_no}".($prize?->prize_name ? " · {$prize->prize_name}" : ''),
                    'base_amount' => null,
                    'rate_percent' => null,
                    'amount' => $prize?->prize_value,
                    'status' => 'paid',
                    'reason' => 'Same prize as the winner',
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, int>  $paymentIds
     * @return array<int, array<string, mixed>>
     */
    private function pairRows(Collection $paymentIds): array
    {
        return PairEntry::with(['member.user', 'sourcePayment'])
            ->whereIn('source_payment_id', $paymentIds)
            ->get()
            ->map(fn (PairEntry $entry): array => [
                'key' => "pair-{$entry->id}",
                'date' => Dates::date($entry->created_at),
                'type' => 'pair_entry',
                ...$this->beneficiary($entry->member),
                'relation' => 'Binary upline · '.ucfirst($entry->side).' leg',
                'source' => $this->paymentLabel($entry->sourcePayment).($entry->metal ? ' · '.ucfirst($entry->metal).' entry' : ''),
                'base_amount' => $entry->sourcePayment->amount,
                'rate_percent' => null,
                'amount' => null,
                'status' => $entry->status === 'consumed' ? 'pair_used' : 'pair_unused',
                'reason' => $entry->status === 'consumed'
                    ? "Made a pair — milestone {$entry->consumed_for_milestone_no}"
                    : 'Waiting for a matching entry on the other leg',
            ])
            ->all();
    }

    /**
     * T-182 — ids of the given source rows whose wallet credit is still `pending` (a held earning).
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, int>
     */
    private function pendingCreditSources(IncomeLedgerCalculation|StoreProfitDistribution $model, Collection $ids): Collection
    {
        return WalletLedgerEntry::where('source_type', $model->getMorphClass())
            ->whereIn('source_id', $ids)
            ->where('entry_type', 'credit')
            ->where('status', 'pending')
            ->pluck('source_id');
    }

    /** @return array{beneficiary_id: int|null, beneficiary_customer_id: string|null, beneficiary_name: string|null} */
    private function beneficiary(?Member $member): array
    {
        return [
            'beneficiary_id' => $member?->id,
            'beneficiary_customer_id' => $member?->customer_id,
            'beneficiary_name' => $member?->user?->name,
        ];
    }

    private function paymentLabel(?Payment $payment): string
    {
        if ($payment === null) {
            return '—';
        }

        return ($payment->type === 'registration' ? 'Registration payment' : 'EMI payment').' · #'.$payment->id;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function saleLabel(?StoreSale $sale): string
    {
        if ($sale === null) {
            return '—';
        }

        return ucfirst(str_replace('_', ' ', $sale->transaction_type))." · {$sale->item_name}".($sale->store ? " · {$sale->store->name}" : '').' · #'.$sale->id;
    }

    private function reasonLabel(?string $reason): ?string
    {
        return $reason === null ? null : (self::SKIP_REASONS[$reason] ?? str_replace('_', ' ', $reason));
    }
}
