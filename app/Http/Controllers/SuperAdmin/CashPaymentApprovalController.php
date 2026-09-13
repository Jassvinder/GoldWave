<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\RejectCashPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §3.1 step 8 / §10.2 — Super Admin confirms a member's cash
 * payment, whether it's the registration payment or a later EMI installment
 * (T-005) — the query is generic by `mode`/`cash_status`, not `type`, so
 * both flow through the same queue. Store-scoped Admin cash approval (§2.1's
 * "Admin/Super Admin") is deferred to T-014's store-initiated payment flow,
 * which has the store context this endpoint doesn't — see PROGRESS.md.
 */
class CashPaymentApprovalController extends Controller
{
    public function index(): Response
    {
        $pending = Payment::with('member.user', 'emiInstallment')
            ->where('mode', 'cash')
            ->where('cash_status', 'pending_verification')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('super-admin/cash-payments', [
            'pending' => $pending,
        ]);
    }

    public function approve(Payment $payment, Request $request, ApproveCashPayment $action): RedirectResponse
    {
        $action($payment, $request->user());

        return back()->with('status', 'Payment approved.');
    }

    public function reject(Payment $payment, Request $request, RejectCashPayment $action): RedirectResponse
    {
        $action($payment, $request->user());

        return back()->with('status', 'Payment rejected.');
    }
}
