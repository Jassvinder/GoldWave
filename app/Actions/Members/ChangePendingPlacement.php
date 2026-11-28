<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Models\MemberPlacementChange;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\User;
use App\Services\BinaryPlacementResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-252 (DOMAIN_LOGIC.md §3.5) — Super Admin corrects a wrong joining before the member is active: a new sponsor and/or
 * side, placed exactly as a new joining would be (the bottom of that side under the given Customer ID).
 *
 * Allowed only while nobody else has joined under the member: below it there may be only the other IDs of its own
 * combined payment (T-251), which move with it. Nothing is counted for a member before activation (pairs, income,
 * team counts all start at the payment approval), so nothing has to be undone.
 *
 * An ID added later to an existing person ("Add ID") keeps the person's first ID as sponsor (§3.4); for it only the
 * place (parent Customer ID + side) changes.
 */
class ChangePendingPlacement
{
    public const PENDING_STATUSES = ['draft', 'payment_pending'];

    private const MAX_PLACEMENT_RETRIES = 3;

    public function __construct(private readonly BinaryPlacementResolver $placementResolver) {}

    /** True for an "Add ID" ID: the person already had an earlier ID, which is its fixed sponsor. */
    public static function sponsorIsFixed(Member $member): bool
    {
        return $member->user_id !== null
            && Member::where('user_id', $member->user_id)->where('id', '<', $member->id)->exists();
    }

    /** Why the member cannot be moved now, or null when it can. Re-checked under locks by `__invoke`. */
    public function blocker(Member $member): ?string
    {
        if (! in_array($member->status, self::PENDING_STATUSES, true)) {
            return 'Only a member who is not active yet can be moved.';
        }

        $payment = $this->registrationPayment($member);

        if ($payment?->status === 'paid') {
            return 'The payment is already approved, so the member can no longer be moved.';
        }

        $group = $this->group($member, $payment);

        if ($group->first()?->id !== $member->id) {
            return "This ID was paid together with other IDs. Change the first one ({$this->label($group->firstOrFail())}); the rest move with it.";
        }

        if ($this->hasOthersBelow($member, $group)) {
            return 'Someone has already joined under this member, so the sponsor and side can no longer be changed.';
        }

        return null;
    }

    /** @param  'left'|'right'  $side */
    public function __invoke(Member $member, string $code, string $side, string $reason, User $by): Member
    {
        $tries = 0;

        while (true) {
            try {
                return DB::transaction(fn (): Member => $this->move($member, trim($code), $side, $reason, $by));
            } catch (QueryException $e) {
                // A registration took the chosen place between the walk and the update: walk again.
                if (! str_contains($e->getMessage(), 'members_placement_unique') || ++$tries >= self::MAX_PLACEMENT_RETRIES) {
                    throw $e;
                }
            }
        }
    }

    /** @param  'left'|'right'  $side */
    private function move(Member $member, string $code, string $side, string $reason, User $by): Member
    {
        // The approval locks the batch, then the payment, then reads the member; the same order here means an
        // approval either finishes first (the status check below then refuses) or waits and sees the new place.
        $payment = $this->registrationPayment($member);

        if ($payment?->payment_batch_id !== null) {
            PaymentBatch::whereKey($payment->payment_batch_id)->lockForUpdate()->first();
        }

        if ($payment !== null) {
            Payment::where(fn ($q) => $payment->payment_batch_id !== null
                ? $q->where('payment_batch_id', $payment->payment_batch_id)
                : $q->whereKey($payment->id))
                ->orderBy('id')->lockForUpdate()->get();
        }

        $member = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
        $group = $this->group($member, $payment?->refresh());

        // Locking the group rows also holds back a registration that would be placed under one of them until this
        // commits (its insert has to check the parent row), so the check below cannot miss a joining in progress.
        Member::whereIn('id', $group->modelKeys())->orderBy('id')->lockForUpdate()->get();

        if (($blocker = $this->blocker($member)) !== null) {
            throw ValidationException::withMessages(['code' => $blocker]);
        }

        $sponsorFixed = self::sponsorIsFixed($member);
        $start = Member::where('customer_id', $code)->where('status', 'active')->first();

        if ($start === null) {
            throw ValidationException::withMessages(['code' => $sponsorFixed
                ? 'No active member has this Customer ID.'
                : 'Invalid sponsor code. The sponsor must be an active member.']);
        }

        $old = [
            'sponsor_id' => $member->sponsor_id,
            'parent_id' => $member->placement_parent_id,
            'side' => $member->placement_side,
        ];

        // Take the member out first, so the walk cannot step into its own place or the IDs under it.
        $member->update(['placement_parent_id' => null, 'placement_side' => null]);
        $placement = $this->placementResolver->resolve($start, $side);
        $newSponsorId = $sponsorFixed ? $member->sponsor_id : $start->id;

        if ($newSponsorId === $old['sponsor_id'] && $placement['parent_id'] === $old['parent_id'] && $placement['side'] === $old['side']) {
            throw ValidationException::withMessages(['code' => 'The member is already there. Nothing to change.']);
        }

        $member->update([
            'sponsor_id' => $newSponsorId,
            'placement_parent_id' => $placement['parent_id'],
            'placement_side' => $placement['side'],
        ]);

        MemberPlacementChange::create([
            'member_id' => $member->id,
            'old_sponsor_id' => $old['sponsor_id'],
            'new_sponsor_id' => $newSponsorId,
            'old_parent_id' => $old['parent_id'],
            'old_side' => $old['side'],
            'new_parent_id' => $placement['parent_id'],
            'new_side' => $placement['side'],
            'reason' => $reason,
            'changed_by' => $by->id,
        ]);

        return $member;
    }

    private function registrationPayment(Member $member): ?Payment
    {
        return Payment::where('member_id', $member->id)->where('type', 'registration')->orderBy('id')->first();
    }

    /**
     * The IDs that move together: every ID of the member's combined payment (first one first), or just the member.
     *
     * @return Collection<int, Member>
     */
    private function group(Member $member, ?Payment $payment): Collection
    {
        if ($payment?->payment_batch_id === null) {
            return new Collection([$member]);
        }

        return Member::whereIn('id', Payment::where('payment_batch_id', $payment->payment_batch_id)->select('member_id'))
            ->orderBy('id')
            ->get();
    }

    /** @param  Collection<int, Member>  $group */
    private function hasOthersBelow(Member $member, Collection $group): bool
    {
        $below = collect(DB::select(
            'WITH RECURSIVE below(id) AS (
                SELECT id FROM members WHERE placement_parent_id = ?
                UNION ALL
                SELECT m.id FROM members m JOIN below b ON m.placement_parent_id = b.id
            ) SELECT id FROM below',
            [$member->id],
        ))->pluck('id')->map(fn ($id): int => (int) $id);

        $groupIds = $group->modelKeys();

        return $below->contains(fn (int $id): bool => ! in_array($id, $groupIds, true))
            || Member::where('sponsor_id', $member->id)->whereNotIn('id', $groupIds)->exists();
    }

    private function label(Member $member): string
    {
        return $member->customer_id ?? "pending ID #{$member->id}";
    }
}
