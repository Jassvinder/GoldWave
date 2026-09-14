<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\StoreDashboardController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Models\BoosterQualification;
use App\Models\DrawGroupMember;
use App\Models\Member;
use App\Models\Store;
use App\Support\Dates;
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
    ): Response {
        $user = $request->user();
        $member = $user?->member;

        if ($member) {
            return $this->memberDashboard($member);
        }

        if ($user?->role === 'super_admin') {
            return $superAdminDashboard->index($request);
        }

        $store = $user ? Store::where('owner_user_id', $user->id)->first() : null;

        if ($store) {
            $request->attributes->set('store', $store);

            return $storeDashboard->index($request);
        }

        return Inertia::render('dashboard');
    }

    private function memberDashboard(Member $member): Response
    {
        $member->load(['membershipPlan', 'emiSchedule.installments']);

        $schedule = $member->emiSchedule;
        $totalInstallments = $schedule?->installments->count() ?? 0;
        $paidInstallments = $schedule?->installments->where('status', 'paid')->count() ?? 0;

        $incomeTotals = $member->incomeLedgerCalculations()
            ->where('eligibility_status', 'paid')
            ->selectRaw('type, sum(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $pairUnusedLeft = $member->pairEntries()->where('side', 'left')->where('status', 'unused')->count();
        $pairUnusedRight = $member->pairEntries()->where('side', 'right')->where('status', 'unused')->count();

        $activeBoosterCount = BoosterQualification::where('member_id', $member->id)
            ->whereHas('payoutSchedules', fn ($q) => $q->where('status', 'pending'))
            ->count();

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
            'wallet_balance' => (string) $member->wallet_balance,
            'direct_count' => $member->directs()->count(),
            'income' => [
                'level_income' => (string) ($incomeTotals['level_income'] ?? 0),
                'purchase_repurchase' => (string) ($incomeTotals['purchase_repurchase'] ?? 0),
            ],
            'pair' => [
                'unused_left' => $pairUnusedLeft,
                'unused_right' => $pairUnusedRight,
            ],
            'booster_active_levels' => $activeBoosterCount,
            'draw_active' => $activeDrawGroup,
            'alerts' => $alerts,
        ]);
    }
}
