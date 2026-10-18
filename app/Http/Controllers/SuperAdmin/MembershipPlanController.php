<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\SaveMembershipPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveMembershipPlanRequest;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanChange;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §3.3 (T-237) — Super Admin's Membership Plans page: list, add and edit plans, with the change history.
 * A plan edit applies to new registrations only; existing members keep their own figures.
 */
class MembershipPlanController extends Controller
{
    public function index(RuleVersionService $rules): Response
    {
        $pair = (array) $rules->value('pair_qualification_emis', []);
        $draw = (array) $rules->value('draw_eligibility_emis', []);
        $dummyPlanCode = (string) $rules->value('dummy_entry_plan_code', 'A');
        // Real members only: the company root and dummy entries not yet given to a leader are left out. An assigned
        // dummy keeps `is_company_dummy = true` but is a real leader now, so it counts.
        $memberCounts = Member::query()
            ->where('is_company_root', false)
            ->where(fn ($query) => $query->where('is_company_dummy', false)->orWhere('dummy_status', 'assigned'))
            ->selectRaw('membership_plan_id, count(*) as total')
            ->groupBy('membership_plan_id')
            ->pluck('total', 'membership_plan_id');

        $plans = MembershipPlan::orderBy('id')->get()->map(fn (MembershipPlan $plan): array => [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'product_category' => $plan->product_category,
            'plan_type' => $plan->isEmiPlan() ? 'emi' : 'direct',
            'amount' => (float) $plan->amount,
            'installment_count' => $plan->installment_count,
            'fixed_weight_grams' => $plan->fixed_weight_grams === null ? null : (float) $plan->fixed_weight_grams,
            'is_active' => (bool) $plan->is_active,
            'pair_emis' => isset($pair[$plan->code]) ? (int) $pair[$plan->code] : null,
            'draw_emis' => isset($draw[$plan->code]) ? (int) $draw[$plan->code] : null,
            'members_count' => (int) ($memberCounts[$plan->id] ?? 0),
            'is_dummy_plan' => $plan->code === $dummyPlanCode,
        ]);

        $history = MembershipPlanChange::with(['membershipPlan', 'changedBy'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (MembershipPlanChange $change): array => [
                'id' => $change->id,
                'plan_name' => $change->after['name'] ?? $change->membershipPlan->name,
                'action' => $change->action,
                'before' => $change->before,
                'after' => $change->after,
                'changed_by' => $change->changedBy->name,
                'changed_on' => Dates::date($change->created_at),
                'changed_time' => $change->created_at?->format('h:i A'),
            ]);

        return Inertia::render('super-admin/membership-plans', [
            'plans' => $plans,
            'history' => $history,
        ]);
    }

    public function store(SaveMembershipPlanRequest $request, SaveMembershipPlan $save): RedirectResponse
    {
        $plan = $save(null, $request->validated(), $request->user());

        return redirect()->route('super-admin.membership-plans.index')
            ->with('status', "Plan {$plan->name} added. It is now offered on registration".($plan->is_active ? '.' : ' once switched on.'));
    }

    public function update(SaveMembershipPlanRequest $request, MembershipPlan $plan, SaveMembershipPlan $save): RedirectResponse
    {
        $save($plan, $request->validated(), $request->user());

        return redirect()->route('super-admin.membership-plans.index')
            ->with('status', "Plan {$plan->name} saved. Changes apply to new registrations only.");
    }
}
