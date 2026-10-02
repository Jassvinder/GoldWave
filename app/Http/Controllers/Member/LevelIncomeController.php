<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\IncomeLedgerCalculation;
use App\Services\PairQualifiedDirects;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md M10 — 12-level Level Income history (DOMAIN_LOGIC.md §6). T-186 — also the income held for too few
 * qualified directs, with how many each level needs, so the member sees what their next directs will release.
 */
class LevelIncomeController extends Controller
{
    public function __construct(
        private readonly PairQualifiedDirects $qualifiedDirects,
        private readonly RuleVersionService $rules,
    ) {}

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $rows = $member->incomeLedgerCalculations()
            ->where('type', 'level_income')
            ->where('eligibility_status', 'paid')
            ->with('sourcePayment.member')
            ->orderByDesc('id')
            ->get()
            ->map(fn (IncomeLedgerCalculation $row): array => [
                'id' => $row->id,
                'level_no' => $row->level_no,
                'rate_percent' => $row->rate_percent,
                'amount' => $row->amount,
                'source_customer_id' => $row->sourcePayment?->member?->customer_id,
                'created_at' => $row->created_at?->toDateString(),
                'released_at' => Dates::date($row->released_at),
            ]);

        $minDirects = (array) $this->rules->value('level_income_min_directs', []);

        $held = $member->incomeLedgerCalculations()
            ->where('type', 'level_income')
            ->where('eligibility_status', 'held')
            ->with('sourcePayment.member')
            ->orderByDesc('id')
            ->get()
            ->map(fn (IncomeLedgerCalculation $row): array => [
                'id' => $row->id,
                'level_no' => $row->level_no,
                'rate_percent' => $row->rate_percent,
                'amount' => $row->amount,
                'source_customer_id' => $row->sourcePayment?->member?->customer_id,
                'created_at' => $row->created_at?->toDateString(),
                'required_directs' => (int) ($minDirects[(string) $row->level_no] ?? 0),
            ]);

        return Inertia::render('member/level-income', [
            'rows' => $rows,
            'total' => (string) $rows->sum('amount'),
            'held' => $held,
            'heldTotal' => (string) $held->sum('amount'),
            'qualifiedDirects' => $held->isEmpty() ? null : $this->qualifiedDirects->count($member),
        ]);
    }
}
