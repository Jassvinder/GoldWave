<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md M17 — "Own downloadable reports". The full multi-report
 * catalog + queued exports (INSTRUCTIONS.md's "Reports" section, covering
 * every module across every role) is T-018's own dedicated task; this page
 * covers only a member's own data, synchronously, since each export here is
 * always small (one member's own rows, never a company-wide dataset that
 * would need PERFORMANCE_GUIDE.md's queued-export treatment).
 */
class ReportsController extends Controller
{
    private const REPORTS = [
        'payments' => 'Payment History',
        'level-income' => 'Level Income',
        'pair-reward' => 'Pair/Reward',
        'wallet' => 'Wallet Ledger',
        'payouts' => 'Payout History',
    ];

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        return Inertia::render('member/reports', [
            'reports' => self::REPORTS,
        ]);
    }

    public function download(Request $request, string $report): HttpResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        [$header, $rows] = match ($report) {
            'payments' => [
                ['Type', 'Amount', 'Mode', 'Status', 'Paid At'],
                $member->payments()->orderByDesc('id')->get()
                    ->map(fn ($p) => [$p->type, $p->amount, $p->mode, $p->status, Dates::date($p->paid_at)]),
            ],
            'level-income' => [
                ['Level', 'Rate %', 'Amount', 'Status', 'Date'],
                $member->incomeLedgerCalculations()->where('type', 'level_income')->orderByDesc('id')->get()
                    ->map(fn ($r) => [$r->level_no, $r->rate_percent, $r->amount, $r->eligibility_status, $r->created_at?->toDateString()]),
            ],
            'pair-reward' => [
                ['Milestone', 'Left', 'Right', 'Reward', 'Month'],
                $member->pairRewardTransactions()->orderByDesc('id')->get()
                    ->map(fn ($r) => [$r->milestone_no, $r->left_consumed_count, $r->right_consumed_count, $r->reward_amount, Dates::date($r->calculated_for_month)]),
            ],
            'wallet' => [
                ['Type', 'Category', 'Amount', 'Status', 'Date'],
                $member->walletLedgerEntries()->orderByDesc('id')->get()
                    ->map(fn ($r) => [$r->entry_type, $r->category, $r->amount, $r->status, $r->created_at?->toDateString()]),
            ],
            'payouts' => [
                ['Requested Amount', 'Status', 'Date'],
                $member->payoutRequests()->orderByDesc('id')->get()
                    ->map(fn ($r) => [$r->requested_amount, $r->status, $r->created_at?->toDateString()]),
            ],
        };

        $csv = implode(',', $header)."\n".$rows->map(fn ($row) => implode(',', $row))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$report}.csv\"",
        ]);
    }
}
