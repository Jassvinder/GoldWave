<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\StoreDashboardController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Models\BoosterQualification;
use App\Models\DrawExecution;
use App\Models\DrawGroupMember;
use App\Models\Member;
use App\Models\Store;
use App\Services\MemberNetworkSummary;
use App\Services\PairPoolBreakdown;
use App\Support\Dates;
use App\Support\Portal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared `/dashboard` route every login redirects to (GoldWaveLoginController
 * and Fortify alike). A member sees the real INSTRUCTIONS.md M01 Dashboard
 * (T-015); an Admin with an assigned store sees the real A01 Store Dashboard
 * (T-016, delegated to `Admin\StoreDashboardController` rather than
 * duplicating its rendering logic here); a Super Admin sees the real S01
 * System Dashboard (T-017, delegated to `SuperAdmin\DashboardController` the
 * same way); anyone else falls back to the untouched generic starter-kit
 * placeholder.
 */
class DashboardController extends Controller
{
    public function index(
        Request $request,
        StoreDashboardController $storeDashboard,
        SuperAdminDashboardController $superAdminDashboard,
        PairPoolBreakdown $pairPool,
        MemberNetworkSummary $network,
    ): Response {
        $user = $request->user();
        $member = $user?->member;

        // T-131: a Store Owner (role=store_admin) who is also a Member sees the dashboard of the door
        // they logged in through — Store ID login => Store Dashboard, Member login => member dashboard.
        $inStorePortal = $user?->isStoreAdmin() === true && Portal::current($request) !== Portal::MEMBER;

        if ($member && ! $inStorePortal) {
            return $this->memberDashboard($member, $pairPool, $network);
        }

        // Super Admin and the company Admin (29-09-2026) share the company dashboard.
        if ($user?->isCompanyStaff() === true) {
            return $superAdminDashboard->index($request);
        }

        $store = $user ? Store::where('owner_user_id', $user->id)->first() : null;

        if ($store) {
            $request->attributes->set('store', $store);

            return $storeDashboard->index($request);
        }

        return Inertia::render('dashboard');
    }

    private function memberDashboard(Member $member, PairPoolBreakdown $pairPool, MemberNetworkSummary $network): Response
    {
        $member->load(['membershipPlan', 'emiSchedule.installments']);

        $schedule = $member->emiSchedule;
        $totalInstallments = $schedule?->installments->count() ?? 0;
        $paidInstallments = $schedule?->installments->where('status', 'paid')->count() ?? 0;

        // Income is read from the wallet ledger itself, so the Income card's total always reconciles with the Wallet card.
        $credited = $member->walletLedgerEntries()
            ->where('entry_type', 'credit')
            ->where('status', 'confirmed')
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $debited = $member->walletLedgerEntries()
            ->where('entry_type', 'debit')
            ->where('status', 'confirmed')
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $heldLevelIncome = $member->incomeLedgerCalculations()
            ->where('type', 'level_income')
            ->where('eligibility_status', 'held')
            ->sum('amount');

        $money = fn (float|int|string|null $value): string => number_format((float) $value, 2, '.', '');

        $pairPoolBreakdown = $pairPool->forMember($member);

        $activeBoosterCount = BoosterQualification::where('member_id', $member->id)
            ->whereHas('payoutSchedules', fn ($q) => $q->where('status', 'pending'))
            ->count();

        // 01-10-2026 (user-requested) — per qualified level: how much Booster income has been paid so far.
        $boosterLevels = BoosterQualification::where('member_id', $member->id)
            ->with('payoutSchedules')
            ->orderBy('level_no')
            ->get()
            ->map(fn (BoosterQualification $qualification): array => [
                'level_no' => $qualification->level_no,
                'received' => number_format((float) $qualification->payoutSchedules->where('status', 'paid')->sum('amount'), 2, '.', ''),
                'months_paid' => $qualification->payoutSchedules->where('status', 'paid')->count(),
                'months_total' => $qualification->payoutSchedules->count(),
            ])
            ->all();

        $activeDrawGroup = DrawGroupMember::where('member_id', $member->id)
            ->where('is_winner_removed', false)
            ->whereHas('drawGroup', fn ($q) => $q->where('status', 'active'))
            ->exists();

        $alerts = [];

        if ($member->pending_fields_submitted_at === null) {
            $alerts[] = 'Complete your Pending Profile fields (PAN, Aadhaar, Bank Details) to unlock Payout requests.';
        }

        if ($member->bankDetails()->whereNull('verified_at')->exists()) {
            $alerts[] = 'Your bank details are awaiting Super Admin verification.';
        }

        return Inertia::render('member/dashboard', [
            'member' => [
                'customer_id' => $member->customer_id,
                'name' => $member->user?->name,
                'status' => $member->status,
                'activated_at' => Dates::date($member->activated_at),
            ],
            'plan' => $member->membershipPlan ? [
                'name' => $member->membershipPlan->name,
                'is_emi_plan' => $member->membershipPlan->isEmiPlan(),
            ] : null,
            'emi' => $schedule ? [
                'paid_installments' => $paidInstallments,
                'total_installments' => $totalInstallments,
            ] : null,
            'wallet' => [
                'balance' => $money($member->wallet_balance),
                'on_hold' => $money($member->wallet_hold_amount),
                'withdrawn' => $money($debited['payout'] ?? 0),
                'used_for_registrations' => $money($debited['assisted_registration'] ?? 0),
            ],
            'direct_count' => $member->directs()->count(),
            'team' => $network->teamCounts($member),
            // Every income type credited to the wallet, plus income earned but not yet credited.
            'income' => [
                'total' => $money($credited->only(['level_income', 'pair_reward', 'booster', 'purchase_repurchase_income', 'store_distribution'])->sum()),
                'level_income' => $money($credited['level_income'] ?? 0),
                'pair_reward' => $money($credited['pair_reward'] ?? 0),
                'booster' => $money($credited['booster'] ?? 0),
                'purchase_repurchase' => $money($credited['purchase_repurchase_income'] ?? 0),
                'store_profit' => $money($credited['store_distribution'] ?? 0),
                'held_level_income' => $money($heldLevelIncome),
                'held_emi_overdue' => $member->heldEarnings(),
            ],
            // Team vs. unused vs. used vs. not-yet-eligible, so the unused count alone never looks like missing entries.
            'pair' => [
                'left' => $pairPoolBreakdown['left'],
                'right' => $pairPoolBreakdown['right'],
                'milestones_achieved' => $member->pairRewardTransactions()->count(),
            ],
            'booster_active_levels' => $activeBoosterCount,
            'booster_levels' => $boosterLevels,
            'draw_active' => $activeDrawGroup,
            // T-199 — draws this member won, and prizes received as a winner's Sponsor (upline benefit).
            'draw' => $this->drawSummary($member),
            'alerts' => $alerts,
        ]);
    }

    /** @return array{wins: array<int, array{group_no: int, cycle_month_no: int, executed_at: string|null, prize_name: string|null, prize_value: string|null}>, won: int, upline_benefits: int, latest_upline: array{prize_name: string|null, prize_value: string|null, winner_customer_id: string|null}|null} */
    private function drawSummary(Member $member): array
    {
        $latestUpline = DrawExecution::with(['winner', 'drawGroup.monthConfigs'])
            ->where('upline_benefit_member_id', $member->id)
            ->latest('executed_at')
            ->first();
        $prize = $latestUpline?->drawGroup->monthConfigs->firstWhere('cycle_month_no', $latestUpline->cycle_month_no);

        // 01-10-2026 (user-requested) — which prize each win brought, not only how many.
        $wins = DrawExecution::with('drawGroup.monthConfigs')
            ->where('winner_member_id', $member->id)
            ->orderByDesc('executed_at')
            ->get()
            ->map(function (DrawExecution $execution): array {
                $config = $execution->drawGroup->monthConfigs->firstWhere('cycle_month_no', $execution->cycle_month_no);

                return [
                    'group_no' => $execution->drawGroup->group_no,
                    'cycle_month_no' => $execution->cycle_month_no,
                    'executed_at' => Dates::date($execution->executed_at),
                    'prize_name' => $config?->prize_name,
                    'prize_value' => $config?->prize_value,
                ];
            })
            ->all();

        return [
            'wins' => $wins,
            'won' => count($wins),
            'upline_benefits' => DrawExecution::where('upline_benefit_member_id', $member->id)->count(),
            'latest_upline' => $latestUpline === null ? null : [
                'prize_name' => $prize?->prize_name,
                'prize_value' => $prize?->prize_value,
                'winner_customer_id' => $latestUpline->winner->customer_id,
            ],
        ];
    }
}
