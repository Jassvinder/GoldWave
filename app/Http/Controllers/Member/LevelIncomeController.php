<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\IncomeLedgerCalculation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M10 — 12-level Level Income history (DOMAIN_LOGIC.md §6). */
class LevelIncomeController extends Controller
{
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
            ]);

        return Inertia::render('member/level-income', [
            'rows' => $rows,
            'total' => (string) $rows->sum('amount'),
        ]);
    }
}
