<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\Draw\ReconcileDrawExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ReconcileDrawExecutionRequest;
use App\Http\Requests\SuperAdmin\SetDrawMonthPrizeRequest;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\DrawGroupMember;
use App\Models\DrawGroupMonthConfig;
use App\Models\Member;
use App\Services\DrawPrizeResolver;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md M13's Admin view / Admin Draw Management — draw cycle/date,
 * generated groups, eligible member count per group, winner, prize,
 * execution status/timestamps, and the manual reconciliation screen (backed
 * by `ReconcileDrawExecution`, DOMAIN_LOGIC.md §21 T-017 pre-coding pass).
 */
class DrawManagementController extends Controller
{
    private const CYCLE_MONTHS = ExecuteMonthlyDraw::CYCLE_MONTHS;

    public function index(Request $request, GenerateDrawGroups $generator, RuleVersionService $rules, DrawPrizeResolver $prizes): Response
    {
        $groups = DrawGroup::with(['monthConfigs', 'executions.winner', 'executions.uplineBenefitMember.user', 'executions.reconciledBy', 'executions.corrections'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DrawGroup $group): array => $this->summarizeGroup($group, $prizes));

        return Inertia::render('super-admin/draw-management', [
            'groups' => $groups,
            'pool' => $this->summarizePool($generator, (int) $rules->value('draw_group_size', 200)),
        ]);
    }

    public function reconcile(ReconcileDrawExecutionRequest $request, DrawExecution $execution, ReconcileDrawExecution $action): RedirectResponse
    {
        $action($execution, $request->user(), $request->string('correction_note')->toString() ?: null);

        return redirect()->route('super-admin.draw-management.index')->with('status', 'Draw marked as verified.');
    }

    /** Sets a group's own prize and number of winners for a month not drawn yet; the metal follows the month (§8.3). */
    public function setMonthPrize(SetDrawMonthPrizeRequest $request, DrawGroup $group): RedirectResponse
    {
        $month = $request->integer('cycle_month_no');

        DrawGroupMonthConfig::updateOrCreate(
            ['draw_group_id' => $group->id, 'cycle_month_no' => $month],
            [
                'prize_name' => $request->string('prize_name')->trim()->toString(),
                'prize_value' => (float) $request->input('prize_value'),
                'metal_type' => $month <= DrawPrizeResolver::SILVER_MONTHS ? 'silver' : 'gold',
                'winners_count' => $request->integer('winners_count'),
            ],
        );

        return redirect()->route('super-admin.draw-management.index')->with('status', "Prize saved for Group #{$group->group_no}, Month {$month}.");
    }

    /** @return array<string, mixed> */
    private function summarizeGroup(DrawGroup $group, DrawPrizeResolver $prizes): array
    {
        $configs = $group->monthConfigs->keyBy('cycle_month_no');
        $nextMonth = ((int) $group->executions->max('cycle_month_no')) + 1;
        $nextConfig = $configs->get($nextMonth);
        $nextPrize = $nextConfig !== null
            ? ['prize_name' => $nextConfig->prize_name, 'prize_value' => (float) $nextConfig->prize_value, 'metal_type' => $nextConfig->metal_type, 'winners_count' => (int) $nextConfig->winners_count]
            : $prizes->defaultFor($nextMonth);

        // Members are grouped in Customer ID order, so the first and last IDs describe the whole group.
        $range = $group->members()
            ->join('members', 'members.id', '=', 'draw_group_members.member_id')
            ->orderBy('members.id')
            ->pluck('members.customer_id');

        $winners = $group->members()->where('is_winner_removed', true)->count();

        return [
            'id' => $group->id,
            'group_no' => $group->group_no,
            'size' => $group->size,
            'status' => $group->status,
            'cycle_started_month' => $group->cycle_started_month->format('F Y'),
            'created_on' => Dates::date($group->created_at),
            'first_customer_id' => $range->first(),
            'last_customer_id' => $range->last(),
            'winners' => $winners,
            'eligible_remaining' => $range->count() - $winners,
            'draws_done' => $nextMonth - 1,
            'cycle_months' => self::CYCLE_MONTHS,
            // The prize the coming draw will carry — the group's own, or the Silver/Gold default (§8.3).
            'next_draw' => $nextMonth > self::CYCLE_MONTHS ? null : [
                'cycle_month_no' => $nextMonth,
                'prize_name' => $nextPrize['prize_name'],
                'prize_value' => number_format($nextPrize['prize_value'], 2, '.', ''),
                'metal_type' => $nextPrize['metal_type'],
                'winners_count' => $nextPrize['winners_count'],
                'is_default' => $nextConfig === null,
            ],
            // Prizes Super Admin set for this group's months still to come.
            'custom_prizes' => $configs->filter(fn (DrawGroupMonthConfig $config): bool => $config->cycle_month_no >= $nextMonth)
                ->sortBy('cycle_month_no')
                ->map(fn (DrawGroupMonthConfig $config): array => [
                    'cycle_month_no' => $config->cycle_month_no,
                    'prize_name' => $config->prize_name,
                    'prize_value' => $config->prize_value,
                    'winners_count' => $config->winners_count,
                ])->values(),
            'executions' => $group->executions->map(fn (DrawExecution $execution): array => $this->summarizeExecution($execution, $configs->get($execution->cycle_month_no))),
        ];
    }

    /**
     * Where every draw-eligible member stands: already in a group, waiting for the next full group, or not yet
     * eligible (EMI members short of their Draw EMIs). Uses the generator's own eligibility rule.
     *
     * @return array<string, int>
     */
    private function summarizePool(GenerateDrawGroups $generator, int $groupSize): array
    {
        $waiting = count($generator->eligibleUngroupedMemberIds());

        $ungrouped = Member::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('is_company_dummy', false)->orWhere('dummy_status', 'assigned'))
            ->where('benefits_limited', false)
            ->whereDoesntHave('drawGroupMemberships')
            ->count();

        return [
            'group_size' => $groupSize,
            'grouped' => DrawGroupMember::count(),
            'waiting' => $waiting,
            'needed_for_next_group' => $groupSize - ($waiting % $groupSize),
            'not_yet_eligible' => $ungrouped - $waiting,
        ];
    }

    /** @return array<string, mixed> */
    private function summarizeExecution(DrawExecution $execution, ?DrawGroupMonthConfig $prize): array
    {
        return [
            'id' => $execution->id,
            'cycle_month_no' => $execution->cycle_month_no,
            'winner_no' => $execution->winner_no,
            'prize_name' => $prize?->prize_name,
            'prize_value' => $prize?->prize_value,
            'metal_type' => $prize?->metal_type,
            'status' => $execution->status,
            'winner_customer_id' => $execution->winner->customer_id,
            'upline_benefit_customer_id' => $execution->uplineBenefitMember?->customer_id,
            'upline_benefit_name' => $execution->uplineBenefitMember?->user?->name,
            'executed_at' => Dates::date($execution->executed_at),
            'reconciled_at' => Dates::date($execution->reconciled_at),
            'reconciled_by' => $execution->reconciledBy?->name,
            'correction_notes' => $execution->corrections->pluck('note'),
        ];
    }
}
