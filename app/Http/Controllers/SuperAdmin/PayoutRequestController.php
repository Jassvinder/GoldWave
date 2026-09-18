<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Payout\FailPayoutRequest;
use App\Actions\Payout\ProcessPayoutRequest;
use App\Actions\Payout\RejectPayoutRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RecordPayoutOutcomeRequest;
use App\Models\PayoutRequest;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-109 (17-09-2026), user-reported — `ProcessPayoutRequest`/
 * `FailPayoutRequest`/`RejectPayoutRequest` have existed since T-009 with
 * no Super Admin page ever wired to them; Payout & TDS Settings is rate
 * configuration, not this queue. Mirrors `CashPaymentApprovalController`'s
 * pending-only-queue shape.
 */
class PayoutRequestController extends Controller
{
    public function index(): Response
    {
        $pending = PayoutRequest::with(['member.user', 'bankDetail'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PayoutRequest $request): array => [
                'id' => $request->id,
                'requested_amount' => $request->requested_amount,
                'created_at' => Dates::date($request->created_at),
                'member' => [
                    'customer_id' => $request->member->customer_id,
                    'name' => $request->member->user?->name,
                ],
                'bank_detail' => $request->bankDetail ? [
                    'account_holder_name' => $request->bankDetail->account_holder_name,
                    'account_number' => $request->bankDetail->account_number,
                    'ifsc_code' => $request->bankDetail->ifsc_code,
                    'bank_name' => $request->bankDetail->bank_name,
                    'verified_at' => Dates::date($request->bankDetail->verified_at),
                ] : null,
            ]);

        return Inertia::render('super-admin/payout-requests', [
            'pending' => $pending,
        ]);
    }

    public function process(RecordPayoutOutcomeRequest $request, PayoutRequest $payout_request, ProcessPayoutRequest $action): RedirectResponse
    {
        $action(
            $payout_request,
            $request->user(),
            $request->string('method')->toString(),
            $request->string('reference')->toString() ?: null,
        );

        return back()->with('status', 'Payout processed.');
    }

    public function fail(RecordPayoutOutcomeRequest $request, PayoutRequest $payout_request, FailPayoutRequest $action): RedirectResponse
    {
        $action(
            $payout_request,
            $request->user(),
            $request->string('method')->toString(),
            $request->string('reference')->toString() ?: null,
        );

        return back()->with('status', 'Payout marked failed; hold released.');
    }

    public function reject(PayoutRequest $payout_request, RejectPayoutRequest $action): RedirectResponse
    {
        $action($payout_request);

        return back()->with('status', 'Payout request rejected.');
    }
}
