<?php

namespace App\Services;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * T-172 (28-09-2026, user-requested first version — the user explicitly
 * allowed assumptions for this page only; they are listed on the page and in
 * Docs/INSTRUCTIONS.md so they can be corrected later). Read-only: the
 * Super Admin's company-wide money and metal picture.
 *
 * Flow figures (collections, member earnings, payouts, store activity,
 * draws) honour the optional from/to date filter. Position figures (wallet
 * balances, plan jewellery still owed, store stock, EMIs still to collect)
 * and the all-time estimate are always "as of today".
 *
 * Assumptions:
 * - Company income = every `paid` registration/EMI payment (store sale money
 *   is collected by the store outside the app, §17.4, so it is not company income).
 * - Member earnings = every confirmed wallet credit (Level, Pair/Reward,
 *   Booster, Draw benefit, Store Profit, Purchase/Repurchase).
 * - Plan jewellery cost: Current Rate schedules and one-time plans owe a fixed
 *   weight (schedule weight, or one-time amount ÷ rate on the entry day),
 *   valued at today's rate; Future Rate schedules owe their rupee commitment.
 * - Draw prize cost = the configured prize value, once for the winner and
 *   once more when a qualifying sponsor also receives it (§8.5).
 */
class CompanyFinancialSummary
{
    private const EARNING_LABELS = [
        'level_income' => 'Level Income',
        'pair_reward' => 'Pair/Reward',
        'booster' => 'Income Booster',
        'draw_benefit' => 'Draw Benefit',
        'store_distribution' => 'Store Profit Distribution',
        'purchase_repurchase_income' => 'Purchase/Repurchase Income',
    ];

    private ?Carbon $from = null;

    private ?Carbon $to = null;

    /** @return array<string, mixed> */
    public function build(?string $from, ?string $to): array
    {
        $this->from = $from ? Carbon::parse($from)->startOfDay() : null;
        $this->to = $to ? Carbon::parse($to)->endOfDay() : null;

        $rates = $this->currentRates();
        $collections = $this->collections();
        $earnings = $this->earnings();
        $jewellery = $this->planJewellery($rates);

        return [
            'rates' => $rates,
            'collections' => $collections,
            'earnings' => $earnings,
            'payouts' => $this->payouts(),
            'positions' => $this->positions(),
            'jewellery' => $jewellery,
            'draws' => $this->drawPrizes(),
            'store' => $this->storeMetal(),
            'estimate' => $this->estimate($jewellery),
        ];
    }

    /** @return array{gold: float|null, silver: float|null} per gram, latest effective today */
    private function currentRates(): array
    {
        $rate = fn (string $metal): ?float => ($value = DB::table('metal_rates')
            ->where('metal', $metal)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('rate_per_gram')) !== null ? (float) $value : null;

        return ['gold' => $rate('gold'), 'silver' => $rate('silver')];
    }

    private function inPeriod(Builder $query, Expression|string $column): Builder
    {
        return $query
            ->when($this->from, fn (Builder $q) => $q->where($column, '>=', $this->from))
            ->when($this->to, fn (Builder $q) => $q->where($column, '<=', $this->to));
    }

    /** @return array<string, mixed> */
    private function collections(): array
    {
        $rows = $this->inPeriod(
            DB::table('payments')
                ->join('members', 'members.id', '=', 'payments.member_id')
                ->leftJoin('membership_plans', 'membership_plans.id', '=', 'members.membership_plan_id')
                ->where('payments.status', 'paid'),
            DB::raw('COALESCE(payments.paid_at, payments.created_at)'),
        )
            ->selectRaw('payments.type, payments.mode, membership_plans.product_category as metal, COUNT(*) as count, SUM(payments.amount) as total')
            ->groupBy('payments.type', 'payments.mode', 'membership_plans.product_category')
            ->get();

        $byType = ['registration' => 0.0, 'emi_installment' => 0.0];
        $byMode = ['cash' => 0.0, 'online' => 0.0, 'wallet' => 0.0];
        $byMetal = ['gold' => 0.0, 'silver' => 0.0];
        $count = 0;

        foreach ($rows as $row) {
            $amount = (float) $row->total;
            $byType[(string) $row->type] = ($byType[(string) $row->type] ?? 0) + $amount;
            $byMode[(string) $row->mode] = ($byMode[(string) $row->mode] ?? 0) + $amount;

            if ($row->metal === 'gold' || $row->metal === 'silver') {
                $byMetal[$row->metal] += $amount;
            }

            $count += (int) $row->count;
        }

        return [
            'total' => array_sum($byType),
            'count' => $count,
            'by_type' => $byType,
            'by_mode' => $byMode,
            'by_metal' => $byMetal,
        ];
    }

    /** @return array{total: float, by_category: list<array{category: string, label: string, count: int, amount: float}>} */
    private function earnings(): array
    {
        $rows = $this->inPeriod(
            DB::table('wallet_ledger_entries')
                ->where('entry_type', 'credit')
                ->where('status', 'confirmed')
                ->whereIn('category', array_keys(self::EARNING_LABELS)),
            'created_at',
        )
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $byCategory = [];

        foreach (self::EARNING_LABELS as $category => $label) {
            $row = $rows->get($category);
            $byCategory[] = [
                'category' => $category,
                'label' => $label,
                'count' => $row ? (int) $row->count : 0,
                'amount' => $row ? (float) $row->total : 0.0,
            ];
        }

        return [
            'total' => array_sum(array_column($byCategory, 'amount')),
            'by_category' => $byCategory,
        ];
    }

    /** @return array<string, float|int> */
    private function payouts(): array
    {
        $processed = $this->inPeriod(
            DB::table('payout_transactions')->where('status', 'processed'),
            'processed_at',
        )
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount_snapshot), 0) as gross, COALESCE(SUM(tds_amount), 0) as tds, COALESCE(SUM(processing_fee), 0) as fee')
            ->first();

        $pending = DB::table('payout_requests')->where('status', 'pending')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(requested_amount), 0) as total')
            ->first();

        $gross = (float) ($processed->gross ?? 0);
        $tds = (float) ($processed->tds ?? 0);
        $fee = (float) ($processed->fee ?? 0);

        return [
            'processed_count' => (int) ($processed->count ?? 0),
            'gross' => $gross,
            'tds' => $tds,
            'fee' => $fee,
            'net_paid' => $gross - $tds - $fee,
            'pending_count' => (int) ($pending->count ?? 0),
            'pending_amount' => (float) ($pending->total ?? 0),
        ];
    }

    /** @return array<string, float> as of today */
    private function positions(): array
    {
        $members = DB::table('members')
            ->selectRaw('COALESCE(SUM(wallet_balance), 0) as balance, COALESCE(SUM(wallet_hold_amount), 0) as hold')
            ->first();

        return [
            'member_wallets' => (float) ($members->balance ?? 0),
            'member_on_hold' => (float) ($members->hold ?? 0),
            'store_wallets' => (float) DB::table('store_wallets')->sum('balance'),
            'company_wallet' => (float) DB::table('company_wallets')->sum('balance'),
            'emis_to_collect' => (float) DB::table('emi_installments')->where('status', '!=', 'paid')->sum('amount'),
            'emis_to_collect_count' => (float) DB::table('emi_installments')->where('status', '!=', 'paid')->count(),
        ];
    }

    /**
     * Plan jewellery owed/delivered per metal, as of today.
     *
     * @param  array{gold: float|null, silver: float|null}  $rates
     * @return array<string, array<string, float|int>>
     */
    private function planJewellery(array $rates): array
    {
        $empty = [
            'entitlements' => 0, 'delivered' => 0, 'pending' => 0,
            'delivered_grams' => 0.0, 'pending_grams' => 0.0,
            'delivered_commitment' => 0.0, 'pending_commitment' => 0.0,
            'delivered_cost' => 0.0, 'pending_cost' => 0.0,
            // Fixed-weight entitlements only: their cost at today's rate vs at the rate they were booked/entered at.
            'fixed_cost_today' => 0.0, 'fixed_cost_booked' => 0.0,
        ];
        $result = ['gold' => $empty, 'silver' => $empty];

        // One-time plans get their `product_benefits` row at activation; an EMI plan's entitlement is its
        // schedule (a benefit row may not exist yet), so each is read from where it actually lives.
        $oneTime = DB::table('product_benefits')
            ->join('membership_plans', 'membership_plans.id', '=', 'product_benefits.membership_plan_id')
            ->whereNull('membership_plans.installment_count')
            ->select([
                'product_benefits.metal', 'product_benefits.delivered_at', 'product_benefits.rate_per_gram_at_entry',
                'membership_plans.product_category', 'membership_plans.amount as plan_amount',
                DB::raw('NULL as rate_booking_method'), DB::raw('NULL as fixed_weight_grams'), DB::raw('NULL as future_commitment_amount'),
                DB::raw('NULL as rate_per_gram_at_booking'),
            ])
            ->get();

        $emi = DB::table('emi_schedules')
            ->join('membership_plans', 'membership_plans.id', '=', 'emi_schedules.membership_plan_id')
            ->leftJoin('product_benefits', 'product_benefits.member_id', '=', 'emi_schedules.member_id')
            ->select([
                'product_benefits.metal', 'product_benefits.delivered_at', DB::raw('NULL as rate_per_gram_at_entry'),
                'membership_plans.product_category', 'membership_plans.amount as plan_amount',
                'emi_schedules.rate_booking_method', 'emi_schedules.fixed_weight_grams', 'emi_schedules.future_commitment_amount',
                'emi_schedules.rate_per_gram_at_booking',
            ])
            ->get();

        foreach ($oneTime->concat($emi) as $row) {
            $metal = $row->metal ?? $row->product_category;

            if ($metal !== 'gold' && $metal !== 'silver') {
                continue;
            }

            $state = $row->delivered_at !== null ? 'delivered' : 'pending';
            $result[$metal]['entitlements']++;
            $result[$metal][$state]++;

            $grams = null;
            $commitment = null;
            $bookedRate = 0.0;

            if ($row->rate_booking_method === null) {
                // One-time plan: jewellery of the paid amount at the entry-day rate — a fixed weight from then on.
                $entryRate = (float) $row->rate_per_gram_at_entry;
                $grams = $entryRate > 0 ? (float) $row->plan_amount / $entryRate : null;
                $commitment = $grams === null ? (float) $row->plan_amount : null;
                $bookedRate = $entryRate;
            } elseif ($row->rate_booking_method === 'current_rate') {
                $grams = (float) $row->fixed_weight_grams;
                $bookedRate = (float) $row->rate_per_gram_at_booking;
            } else {
                $commitment = (float) $row->future_commitment_amount;
            }

            if ($grams !== null) {
                $todayCost = $grams * (float) ($rates[$metal] ?? 0);
                $result[$metal]["{$state}_grams"] += $grams;
                $result[$metal]["{$state}_cost"] += $todayCost;
                $result[$metal]['fixed_cost_today'] += $todayCost;
                $result[$metal]['fixed_cost_booked'] += $grams * $bookedRate;
            }

            if ($commitment !== null) {
                $result[$metal]["{$state}_commitment"] += $commitment;
                $result[$metal]["{$state}_cost"] += $commitment;
            }
        }

        return $result;
    }

    /** @return array<string, array{draws: int, upline_benefits: int, value: float}> */
    private function drawPrizes(bool $filtered = true): array
    {
        $query = DB::table('draw_executions')
            ->join('draw_group_month_configs', function ($join) {
                $join->on('draw_group_month_configs.draw_group_id', '=', 'draw_executions.draw_group_id')
                    ->on('draw_group_month_configs.cycle_month_no', '=', 'draw_executions.cycle_month_no');
            });

        if ($filtered) {
            $query = $this->inPeriod($query, 'draw_executions.executed_at');
        }

        $rows = $query
            ->selectRaw('draw_group_month_configs.metal_type as metal, COUNT(*) as draws, COUNT(draw_executions.upline_benefit_member_id) as upline, SUM(draw_group_month_configs.prize_value) as value, SUM(CASE WHEN draw_executions.upline_benefit_member_id IS NOT NULL THEN draw_group_month_configs.prize_value ELSE 0 END) as upline_value')
            ->groupBy('draw_group_month_configs.metal_type')
            ->get()
            ->keyBy('metal');

        $result = [];

        foreach (['gold', 'silver'] as $metal) {
            $row = $rows->get($metal);
            $result[$metal] = [
                'draws' => $row ? (int) $row->draws : 0,
                'upline_benefits' => $row ? (int) $row->upline : 0,
                'value' => $row ? (float) $row->value + (float) $row->upline_value : 0.0,
            ];
        }

        return $result;
    }

    /** @return array<string, array<string, float|int>> */
    private function storeMetal(): array
    {
        $sales = $this->inPeriod(DB::table('store_sales')->where('status', 'confirmed'), 'created_at')
            ->selectRaw('metal, COUNT(*) as count, COALESCE(SUM(COALESCE(item_weight, 0) * quantity), 0) as grams, COALESCE(SUM(sale_amount), 0) as amount, COALESCE(SUM(gst_amount), 0) as gst')
            ->groupBy('metal')
            ->get()
            ->keyBy('metal');

        $buybacks = $this->inPeriod(DB::table('store_buybacks'), 'occurred_at')
            ->selectRaw('metal, COUNT(*) as count, COALESCE(SUM(weight * quantity), 0) as grams, COALESCE(SUM(price_paid), 0) as amount')
            ->groupBy('metal')
            ->get()
            ->keyBy('metal');

        $stock = DB::table('store_inventory_items')
            ->selectRaw('metal, COALESCE(SUM(quantity), 0) as pieces, COALESCE(SUM(weight * quantity), 0) as grams')
            ->groupBy('metal')
            ->get()
            ->keyBy('metal');

        $result = [];

        foreach (['gold', 'silver'] as $metal) {
            $sale = $sales->get($metal);
            $buyback = $buybacks->get($metal);
            $held = $stock->get($metal);

            $result[$metal] = [
                'sales_count' => $sale ? (int) $sale->count : 0,
                'sales_grams' => $sale ? (float) $sale->grams : 0.0,
                'sales_amount' => $sale ? (float) $sale->amount : 0.0,
                'sales_gst' => $sale ? (float) $sale->gst : 0.0,
                'buyback_count' => $buyback ? (int) $buyback->count : 0,
                'buyback_grams' => $buyback ? (float) $buyback->grams : 0.0,
                'buyback_amount' => $buyback ? (float) $buyback->amount : 0.0,
                'stock_pieces' => $held ? (int) $held->pieces : 0,
                'stock_grams' => $held ? (float) $held->grams : 0.0,
            ];
        }

        return $result;
    }

    /**
     * All-time, as of today — never filtered, so the figures are comparable.
     *
     * @param  array<string, array<string, float|int>>  $jewellery
     * @return array<string, float>
     */
    private function estimate(array $jewellery): array
    {
        $collected = (float) DB::table('payments')->where('status', 'paid')->sum('amount');
        $toCollect = (float) DB::table('emi_installments')->where('status', '!=', 'paid')->sum('amount');
        $earnings = (float) DB::table('wallet_ledger_entries')
            ->where('entry_type', 'credit')->where('status', 'confirmed')
            ->whereIn('category', array_keys(self::EARNING_LABELS))
            ->sum('amount');

        $jewelleryCost = 0.0;
        $rateImpact = 0.0;

        foreach ($jewellery as $metal) {
            $jewelleryCost += (float) $metal['delivered_cost'] + (float) $metal['pending_cost'];
            // 29-09-2026 (user request) — how much of the jewellery cost is only because today's rate differs from
            // the rate fixed-weight jewellery was booked/entered at (negative = rate fell, a saving).
            $rateImpact += (float) $metal['fixed_cost_today'] - (float) $metal['fixed_cost_booked'];
        }

        $drawCost = array_sum(array_column($this->drawPrizes(filtered: false), 'value'));

        $income = $collected + $toCollect;
        $cost = $earnings + $jewelleryCost + $drawCost;

        return [
            'collected' => $collected,
            'to_collect' => $toCollect,
            'expected_income' => $income,
            'member_earnings' => $earnings,
            'jewellery_cost' => $jewelleryCost,
            'draw_cost' => $drawCost,
            'total_cost' => $cost,
            'surplus' => $income - $cost,
            'rate_impact' => $rateImpact,
            'surplus_at_booking_rates' => $income - $cost + $rateImpact,
        ];
    }
}
