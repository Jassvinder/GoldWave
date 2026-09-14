<?php

namespace App\Services;

use App\Models\BoosterPayoutSchedule;
use App\Models\DrawExecution;
use App\Models\DrawGroupMonthConfig;
use App\Models\EmiInstallment;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\PairRewardTransaction;
use App\Models\Payment;
use App\Models\PayoutTransaction;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Models\WalletLedgerEntry;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * T-018 — one method per `INSTRUCTIONS.md` "Reports" table row. Each method
 * returns `{header, rows}`; `rows` is a `cursor()`-backed `LazyCollection`
 * of already-flattened arrays (never `get()->all()`, per
 * `PERFORMANCE_GUIDE.md`'s query-efficiency note — these tables can hold
 * tens of thousands of rows). Filters are deliberately generic across every
 * report (`DOMAIN_LOGIC.md` §21's T-018 pre-coding entry, decision #4):
 * optional `date_from`/`date_to` on that report's natural date column, plus
 * `customer_id` (member-scoped reports) or `store_id`
 * (Store Sales/Distribution) where structurally meaningful.
 */
class ReportCatalog
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function membership(array $filters): array
    {
        $query = Member::query()->with(['membershipPlan', 'sponsor', 'placementParent'])
            ->orderBy('id');

        $this->applyDateRange($query, 'activated_at', $filters);
        $this->applyCustomerId($query, $filters);

        return [
            'header' => ['Customer ID', 'Plan', 'Sponsor', 'Placement Parent', 'Placement Side', 'Status', 'Join Date'],
            'rows' => $query->cursor()->map(function (Member $m) {
                return [
                    $m->customer_id,
                    $this->orDefault($m->membershipPlan, 'name'),
                    $this->orDefault($m->sponsor, 'customer_id'),
                    $this->orDefault($m->placementParent, 'customer_id'),
                    $m->placement_side ?? '—',
                    $m->status,
                    Dates::display($m->activated_at),
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function emi(array $filters): array
    {
        $query = EmiInstallment::query()->with(['emiSchedule.member', 'emiSchedule.membershipPlan', 'payment'])
            ->orderBy('id');

        $this->applyDateRange($query, 'due_date', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('emiSchedule.member', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Plan', 'Member', 'Installment No.', 'Due Date', 'Paid Date', 'Status'],
            'rows' => $query->cursor()->map(function (EmiInstallment $i) {
                $schedule = $i->emiSchedule;

                return [
                    $schedule !== null ? $this->orDefault($schedule->membershipPlan, 'name') : '—',
                    $schedule !== null ? $this->orDefault($schedule->member, 'customer_id') : '—',
                    $i->installment_no,
                    Dates::display($i->due_date),
                    Dates::display($i->payment?->paid_at),
                    $i->status,
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function levelIncome(array $filters): array
    {
        $query = IncomeLedgerCalculation::query()->where('type', 'level_income')
            ->with(['beneficiary', 'sourcePayment'])
            ->orderBy('id');

        $this->applyDateRange($query, 'created_at', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('beneficiary', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Source Payment', 'Member', 'Level', 'Rate %', 'Amount', 'Status', 'Date'],
            'rows' => $query->cursor()->map(function (IncomeLedgerCalculation $r) {
                return [
                    $r->source_payment_id ?? '—',
                    $this->orDefault($r->beneficiary, 'customer_id'),
                    $r->level_no ?? '—',
                    $r->rate_percent,
                    $r->amount,
                    $r->eligibility_status,
                    Dates::display($r->created_at),
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function pairReward(array $filters): array
    {
        $query = PairRewardTransaction::query()->with('member')->orderBy('id');

        $this->applyDateRange($query, 'calculated_for_month', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('member', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Member', 'Milestone', 'Left Consumed', 'Right Consumed', 'Reward', 'Month'],
            'rows' => $query->cursor()->map(function (PairRewardTransaction $r) {
                return [
                    $this->orDefault($r->member, 'customer_id'),
                    $r->milestone_no,
                    $r->left_consumed_count,
                    $r->right_consumed_count,
                    $r->reward_amount,
                    Dates::display($r->calculated_for_month),
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function draw(array $filters): array
    {
        $query = DrawExecution::query()->with(['drawGroup', 'winner', 'uplineBenefitMember'])
            ->orderBy('id');

        $this->applyDateRange($query, 'executed_at', $filters);

        return [
            'header' => ['Cycle Month', 'Group', 'Member Count', 'Winner', 'Prize', 'Upline Benefit', 'Executed At'],
            'rows' => $query->cursor()->map(function (DrawExecution $e) {
                $group = $e->drawGroup;
                $monthConfig = DrawGroupMonthConfig::where('draw_group_id', $group->id)
                    ->where('cycle_month_no', $e->cycle_month_no)->first();

                return [
                    $e->cycle_month_no,
                    $group->group_no,
                    $group->members()->count(),
                    $this->orDefault($e->winner, 'customer_id'),
                    $monthConfig !== null ? "{$monthConfig->prize_name} (₹{$monthConfig->prize_value})" : '—',
                    $this->orDefault($e->uplineBenefitMember, 'customer_id'),
                    Dates::display($e->executed_at),
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function booster(array $filters): array
    {
        $query = BoosterPayoutSchedule::query()->with('boosterQualification.member')
            ->orderBy('id');

        $this->applyDateRange($query, 'scheduled_date', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('boosterQualification.member', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Member', 'Level', 'Qualified At', 'Month No.', 'Amount', 'Status'],
            'rows' => $query->cursor()->map(function (BoosterPayoutSchedule $s) {
                $qualification = $s->boosterQualification;

                return [
                    $this->orDefault($qualification->member, 'customer_id'),
                    $qualification->level_no,
                    Dates::display($qualification->qualified_at),
                    $s->month_no,
                    $s->amount,
                    $s->status,
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function paymentIn(array $filters): array
    {
        $query = Payment::query()->with('member')->orderBy('id');

        $this->applyDateRange($query, 'paid_at', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('member', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Member', 'Type', 'Mode', 'Reference', 'Amount', 'Status', 'Date'],
            'rows' => $query->cursor()->map(function (Payment $p) {
                return [
                    $this->orDefault($p->member, 'customer_id'),
                    $p->type,
                    $p->mode,
                    $p->provider_reference ?? '—',
                    $p->amount,
                    $p->status,
                    $p->paid_at !== null ? Dates::display($p->paid_at) : Dates::display($p->created_at),
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function paymentOut(array $filters): array
    {
        $query = PayoutTransaction::query()->with('payoutRequest.member')->orderBy('id');

        $this->applyDateRange($query, 'processed_at', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('payoutRequest.member', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Recipient', 'Amount', 'Mode', 'Reference', 'Status', 'Date'],
            'rows' => $query->cursor()->map(function (PayoutTransaction $t) {
                return [
                    $this->orDefault($t->payoutRequest->member, 'customer_id'),
                    $t->amount_snapshot,
                    $t->method,
                    $t->reference ?? '—',
                    $t->status,
                    Dates::display($t->processed_at),
                ];
            }),
        ];
    }

    /**
     * No per-entry running balance is stored anywhere in the schema — only
     * `Member.wallet_balance`, the member's *current* balance, which cannot
     * be attributed to a specific historical row without replaying the
     * entire ledger. Per the T-018 pre-coding decision, this column is
     * omitted rather than invented; the member's *current* balance is shown
     * alongside each row instead, for context.
     *
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function walletLedger(array $filters): array
    {
        $query = WalletLedgerEntry::query()->with('member')->orderBy('id');

        $this->applyDateRange($query, 'processed_at', $filters);

        if ($customerId = $filters['customer_id'] ?? null) {
            $query->whereHas('member', fn ($q) => $q->where('customer_id', $customerId));
        }

        return [
            'header' => ['Member', 'Type', 'Category', 'Amount', 'Status', 'Member Current Balance', 'Date'],
            'rows' => $query->cursor()->map(function (WalletLedgerEntry $e) {
                return [
                    $e->member->customer_id,
                    $e->entry_type,
                    $e->category,
                    $e->amount,
                    $e->status,
                    $e->member->wallet_balance,
                    $e->processed_at !== null ? Dates::display($e->processed_at) : Dates::display($e->created_at),
                ];
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function storeSales(array $filters): array
    {
        $query = StoreSale::query()->with(['store', 'member'])->orderBy('id');

        $this->applyDateRange($query, 'created_at', $filters);

        if ($storeId = $filters['store_id'] ?? null) {
            $query->where('store_id', $storeId);
        }

        return [
            'header' => ['Store', 'Item', 'Member', 'Sale Amount', 'Total Invoice Amount', 'Distribution Status', 'Date'],
            'rows' => $query->cursor()->map(function (StoreSale $s) {
                $member = $s->member;

                return [
                    $this->orDefault($s->store, 'name'),
                    $s->item_name,
                    $member !== null ? $member->customer_id : 'Walk-in',
                    $s->sale_amount,
                    $s->total_invoice_amount,
                    $s->distribution_status,
                    Dates::display($s->created_at),
                ];
            }),
        ];
    }

    /**
     * `beneficiary_type` (store_owner / sponsor_level_1..3) is this report's
     * "owner/L1/L2/L3" column — one row per beneficiary per sale, matching
     * `StoreProfitDistribution`'s existing one-row-per-beneficiary shape
     * (T-014).
     *
     * @param  array<string, mixed>  $filters
     * @return array{header: array<int, string>, rows: LazyCollection<int, mixed>}
     */
    public function storeDistribution(array $filters): array
    {
        $query = StoreProfitDistribution::query()->with(['storeSale.store', 'beneficiary'])
            ->orderBy('id');

        $this->applyDateRange($query, 'created_at', $filters);

        if ($storeId = $filters['store_id'] ?? null) {
            $query->whereHas('storeSale', fn ($q) => $q->where('store_id', $storeId));
        }

        return [
            'header' => ['Store', 'Beneficiary Level', 'Beneficiary', 'Rate %', 'Amount', 'Source Sale', 'Date'],
            'rows' => $query->cursor()->map(function (StoreProfitDistribution $d) {
                $store = $d->storeSale?->store;

                return [
                    $this->orDefault($store, 'name'),
                    $d->beneficiary_type,
                    $this->orDefault($d->beneficiary, 'customer_id'),
                    $d->rate_percent,
                    $d->amount,
                    $d->store_sale_id,
                    Dates::display($d->created_at),
                ];
            }),
        ];
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRange(Builder $query, string $column, array $filters): void
    {
        if ($from = $filters['date_from'] ?? null) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to = $filters['date_to'] ?? null) {
            $query->whereDate($column, '<=', $to);
        }
    }

    /**
     * @param  Builder<Member>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCustomerId(Builder $query, array $filters): void
    {
        if ($customerId = $filters['customer_id'] ?? null) {
            $query->where('customer_id', $customerId);
        }
    }

    /**
     * Avoids the `nullsafe.neverNull` Larastan false positive on the
     * `$x?->prop ?? $default` pattern (already worked around this way
     * several times elsewhere in this codebase, e.g.
     * `app/Http/Controllers/Admin/*.php`).
     */
    private function orDefault(?object $object, string $property, string $default = '—'): string
    {
        return $object !== null ? (string) $object->{$property} : $default;
    }
}
