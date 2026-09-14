<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BoosterPayoutSchedule;
use App\Models\DrawExecution;
use App\Models\DrawGroup;
use App\Models\EmiInstallment;
use App\Models\IncomeLedgerCalculation;
use App\Models\Member;
use App\Models\PairRewardTransaction;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\ProfileChangeRequest;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S01/"Admin Dashboard — What Appears" — global health, business controls, queues, alerts. */
class DashboardController extends Controller
{
    public function __construct(private readonly RuleVersionService $rules) {}

    public function index(Request $request): Response
    {
        $members = [
            'total' => Member::count(),
            'active' => Member::where('status', 'active')->count(),
            'pending' => Member::whereIn('status', ['draft', 'payment_pending', 'payment_confirmed'])->count(),
            'today' => Member::whereDate('created_at', today())->count(),
        ];

        $payments = [
            'paid' => Payment::where('status', 'paid')->count(),
            'pending' => Payment::where('status', 'pending')->count(),
            'failed' => Payment::where('status', 'failed')->count(),
        ];

        $emi = [
            'due' => EmiInstallment::where('status', 'due')->count(),
            'overdue' => EmiInstallment::where('status', 'overdue')->count(),
        ];

        $income = [
            'level_income' => (string) IncomeLedgerCalculation::where('type', 'level_income')->where('eligibility_status', 'paid')->sum('amount'),
            'pair_reward' => (string) PairRewardTransaction::sum('reward_amount'),
            'booster' => (string) BoosterPayoutSchedule::where('status', 'paid')->sum('amount'),
        ];

        $payouts = [
            'pending' => PayoutRequest::where('status', 'pending')->count(),
            'processed' => PayoutRequest::where('status', 'processed')->count(),
        ];

        $lastDraw = DrawExecution::orderByDesc('executed_at')->first();
        $upcomingDrawGroupCount = DrawGroup::where('status', 'active')->count();

        $dummyStatus = [
            'enabled' => (bool) $this->rules->value('dummy_entry_enabled', false),
            'daily_count' => (int) $this->rules->value('dummy_entry_daily_count', 0),
            'unassigned' => Member::where('is_company_dummy', true)->where('dummy_status', 'unassigned')->count(),
        ];

        $store = [
            'sales_total' => (string) StoreSale::where('status', 'confirmed')->sum('total_invoice_amount'),
            'sales_count' => StoreSale::where('status', 'confirmed')->count(),
            'distribution_total' => (string) StoreProfitDistribution::sum('amount'),
        ];

        $largePayoutThreshold = (float) $this->rules->value('large_payout_threshold', 50000);

        $alerts = [];
        $pendingCashPayments = Payment::where('mode', 'cash')->where('cash_status', 'pending_verification')->count();

        if ($pendingCashPayments > 0) {
            $alerts[] = "{$pendingCashPayments} cash payment(s) awaiting verification.";
        }

        $pendingChangeRequests = ProfileChangeRequest::where('status', 'pending')->count();

        if ($pendingChangeRequests > 0) {
            $alerts[] = "{$pendingChangeRequests} profile change request(s) awaiting review.";
        }

        $largePayoutCount = PayoutRequest::where('status', 'pending')->where('requested_amount', '>=', $largePayoutThreshold)->count();

        if ($largePayoutCount > 0) {
            $alerts[] = "{$largePayoutCount} large-value payout request(s) (₹{$largePayoutThreshold}+) awaiting processing.";
        }

        return Inertia::render('super-admin/dashboard', [
            'members' => $members,
            'payments' => $payments,
            'emi' => $emi,
            'income' => $income,
            'payouts' => $payouts,
            'draw' => [
                'active_groups' => $upcomingDrawGroupCount,
                'last_winner_customer_id' => $lastDraw?->winner?->customer_id,
                'last_executed_at' => $lastDraw ? Dates::date($lastDraw->executed_at) : null,
            ],
            'dummy_entries' => $dummyStatus,
            'store' => $store,
            'alerts' => $alerts,
        ]);
    }
}
