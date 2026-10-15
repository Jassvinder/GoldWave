<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Payments\RejectCashPayment;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCashPaymentApproval;
use App\Models\Payment;
use App\Services\Payments\PaymentModes;
use App\Support\Dates;
use App\Support\PaymentInProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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
    public function index(Request $request): Response
    {
        // T-196 — Cash and GPay/UPI share this queue; a UPI row carries its Ref ID and payment screenshot.
        $pending = Payment::with('member.user', 'emiInstallment')
            ->whereIn('mode', PaymentModes::NEEDS_APPROVAL)
            ->where('cash_status', 'pending_verification')
            ->orderBy('created_at')
            ->get()
            ->each(fn (Payment $payment) => $payment->setAttribute(
                'upi_screenshot_url',
                // T-206 — served from the private disk through an authorised route, never a public URL.
                $payment->upi_screenshot_path ? route('super-admin.payments.screenshot', $payment) : null,
            )->setAttribute(
                // T-228 — its approval is running in the background.
                'approving',
                PaymentInProgress::isRunning($payment->id),
            ));

        $tab = $request->query('tab') === 'history' ? 'history' : 'pending';

        return Inertia::render('super-admin/cash-payments', [
            'tab' => $tab,
            'pending' => $pending,
            // T-226 — loaded only when the History tab is open; the tab label shows its total either way.
            'history' => $tab === 'history' ? $this->history($request) : null,
            'history_total' => $this->decided()->count(),
            'filters' => $request->only(['search', 'status', 'mode', 'from', 'to']),
        ]);
    }

    /**
     * T-226 (15-10-2026, user request) — every Cash / GPay-UPI payment that left the queue, newest decision first, with
     * who decided it and when. Searchable by Customer ID, name or UPI Ref ID; filterable by status, mode and date.
     *
     * @return array<string, mixed> the paginator as the page receives it (`data`, `links`, `current_page`, …)
     */
    private function history(Request $request): array
    {
        $query = $this->decided()->with(['member.user', 'emiInstallment', 'verifiedBy', 'payingStore']);

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(fn (Builder $q) => $q
                ->whereLike('upi_reference', "%{$search}%")
                ->orWhereHas('member', fn (Builder $m) => $m
                    ->whereLike('customer_id', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $u) => $u->whereLike('name', "%{$search}%"))));
        }

        if (in_array($request->query('status'), ['approved', 'rejected'], true)) {
            $query->where('cash_status', $request->query('status'));
        }

        if (in_array($request->query('mode'), PaymentModes::NEEDS_APPROVAL, true)) {
            $query->where('mode', $request->query('mode'));
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = $request->query($key);

            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $query->whereDate('verified_at', $operator, $date);
            }
        }

        return $query->orderByDesc('verified_at')->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'type' => $payment->type,
                'covers_installments' => $payment->covers_installments,
                'installment_no' => $payment->emiInstallment?->installment_no,
                'mode' => $payment->mode,
                'amount' => $payment->amount,
                'cash_status' => $payment->cash_status,
                'customer_id' => $payment->member->customer_id,
                'name' => $payment->member->user?->name,
                'member_id' => $payment->member_id,
                'upi_reference' => $payment->upi_reference,
                'upi_screenshot_url' => $payment->upi_screenshot_path ? route('super-admin.payments.screenshot', $payment) : null,
                'submitted_on' => Dates::date($payment->created_at),
                'decided_on' => Dates::date($payment->verified_at),
                'decided_time' => $payment->verified_at ? Carbon::parse($payment->verified_at)->format('h:i A') : null,
                'decided_by' => $payment->verifiedBy?->name,
                // Cash a store collected and paid from its Store Wallet (§12.2(a)).
                'store_name' => $payment->payingStore?->name,
            ])
            ->toArray();
    }

    /** @return Builder<Payment> */
    private function decided(): Builder
    {
        return Payment::query()
            ->whereIn('mode', PaymentModes::NEEDS_APPROVAL)
            ->whereIn('cash_status', ['approved', 'rejected']);
    }

    /**
     * T-228 (15-10-2026, user decision) — queues the approval (`ProcessCashPaymentApproval`) and returns at once. If it
     * fails, nothing is kept and the payment stays in Pending.
     */
    public function approve(Payment $payment, Request $request): RedirectResponse
    {
        if (! PaymentModes::needsApproval($payment->mode)) {
            throw ValidationException::withMessages(['payment' => 'This payment does not need a manual approval.']);
        }

        if ($payment->cash_status !== 'pending_verification') {
            return back()->with('status', 'This payment has already been decided.');
        }

        if (! PaymentInProgress::claim($payment->id)) {
            return back()->with('status', 'This payment is already being processed.');
        }

        ProcessCashPaymentApproval::dispatch($payment, $request->user());

        return back()->with('status', 'Approval started. It is completed in the background within a few seconds.');
    }

    public function reject(Payment $payment, Request $request, RejectCashPayment $action): RedirectResponse
    {
        if (PaymentInProgress::isRunning($payment->id)) {
            return back()->with('status', 'This payment is being processed and can no longer be rejected.');
        }

        $action($payment, $request->user());

        return back()->with('status', 'Payment rejected.');
    }
}
