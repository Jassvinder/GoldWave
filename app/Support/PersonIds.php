<?php

namespace App\Support;

use App\Actions\Registration\RegisterMemberIds;
use App\Http\Requests\Members\AddMemberIdsRequest;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Payments\PaymentModes;
use Illuminate\Http\RedirectResponse;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — what My IDs (member) and Member Detail (staff) show about one person's IDs, the
 * "Add ID" form's options, and the shared finish of an "Add ID" request.
 */
final class PersonIds
{
    /** @return list<array<string, mixed>> */
    public static function list(User $user): array
    {
        $ids = Member::with(['membershipPlan:id,name', 'placementParent:id,customer_id', 'sponsor:id,customer_id', 'payments.batch'])
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        return array_values($ids->map(function (Member $member) use ($ids): array {
            $payment = $member->payments->firstWhere('type', 'registration');

            return [
                'id' => $member->id,
                'customer_id' => $member->customer_id,
                'status' => $member->status,
                'is_first' => $member->id === $ids->first()?->id,
                'plan' => $member->membershipPlan?->name,
                'sponsor' => $member->sponsor?->customer_id,
                // A pending parent has no Customer ID yet: it is one of this person's IDs, so "ID 2 · Left".
                'placement' => $member->placementParent
                    ? ($member->placementParent->customer_id
                        ?? 'ID '.((int) $ids->search(fn (Member $id) => $id->id === $member->placement_parent_id) + 1)).' · '.ucfirst((string) $member->placement_side)
                    : null,
                'joined_on' => Dates::date($member->activated_at ?? $member->created_at),
                'payment' => $payment ? [
                    'mode' => $payment->mode,
                    'cash_status' => $payment->cash_status,
                    'amount' => (string) $payment->amount,
                    'combined_amount' => $payment->batch?->amount !== null ? (string) $payment->batch->amount : null,
                ] : null,
            ];
        })->all());
    }

    /** @return array<string, mixed> the "Add ID" form's options for this person */
    public static function addForm(User $user): array
    {
        $held = Member::where('user_id', $user->id)->count();
        $limit = MemberIds::limit();

        return [
            'limit' => $limit,
            'held' => $held,
            'can_add' => max(0, $limit - $held),
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get(['id', 'name', 'amount', 'installment_count', 'product_category']),
            'payment_options' => [...PaymentModes::forPage(), 'modes' => PaymentModes::NEEDS_APPROVAL],
        ];
    }

    /** Runs "Add ID", stores the proof on the payment, and goes back with a short summary. */
    public static function add(User $owner, AddMemberIdsRequest $request, User $operator): RedirectResponse
    {
        $members = app(RegisterMemberIds::class)->forExistingPerson($owner, $request->validated(), $operator);

        $payment = $members->firstOrFail()->payments->firstWhere('type', 'registration');
        $batch = $payment?->batch;
        PaymentModes::attachUpiProof($batch ?? $payment, $request);

        $count = $members->count();
        $total = '₹'.number_format((float) ($batch->amount ?? $payment->amount ?? 0), 2);
        $what = $count === 1 ? '1 ID added' : "{$count} IDs added";

        return back()->with('status', "{$what}. They become active once the payment of {$total} is approved on Payment Approvals.");
    }
}
