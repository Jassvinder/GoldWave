<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Draw\ReconcileDrawExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ReconcileDrawExecutionRequest;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
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
    public function index(Request $request): Response
    {
        $groups = DrawGroup::with(['executions.winner', 'executions.uplineBenefitMember', 'executions.reconciledBy', 'executions.corrections'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DrawGroup $group): array => $this->summarizeGroup($group));

        return Inertia::render('super-admin/draw-management', [
            'groups' => $groups,
        ]);
    }

    public function reconcile(ReconcileDrawExecutionRequest $request, DrawExecution $execution, ReconcileDrawExecution $action): RedirectResponse
    {
        $action($execution, $request->user(), $request->string('correction_note')->toString() ?: null);

        return redirect()->route('super-admin.draw-management.index')->with('status', 'Draw execution reconciled.');
    }

    /** @return array<string, mixed> */
    private function summarizeGroup(DrawGroup $group): array
    {
        return [
            'id' => $group->id,
            'group_no' => $group->group_no,
            'size' => $group->size,
            'status' => $group->status,
            'cycle_started_month' => Dates::date($group->cycle_started_month),
            'eligible_remaining' => $group->members()->where('is_winner_removed', false)->count(),
            'executions' => $group->executions->map(fn (DrawExecution $execution): array => $this->summarizeExecution($execution)),
        ];
    }

    /** @return array<string, mixed> */
    private function summarizeExecution(DrawExecution $execution): array
    {
        return [
            'id' => $execution->id,
            'cycle_month_no' => $execution->cycle_month_no,
            'status' => $execution->status,
            'winner_customer_id' => $execution->winner->customer_id,
            'upline_benefit_customer_id' => $execution->uplineBenefitMember?->customer_id,
            'executed_at' => Dates::date($execution->executed_at),
            'reconciled_at' => Dates::date($execution->reconciled_at),
            'reconciled_by' => $execution->reconciledBy?->name,
            'correction_notes' => $execution->corrections->pluck('note'),
        ];
    }
}
