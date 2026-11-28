<?php

namespace App\Actions\Registration;

use App\Models\Member;
use App\Models\MemberBankDetail;
use App\Models\MembershipPlan;
use App\Models\PaymentBatch;
use App\Models\User;
use App\Notifications\CashPaymentAwaitingApproval;
use App\Services\BinaryPlacementResolver;
use App\Services\Notifier;
use App\Services\Payments\PaymentModes;
use App\Support\MemberIds;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — several IDs for one person in one request, all on one plan and paid with one combined
 * Cash / GPay-UPI payment (`payment_batches`):
 * - `forNewPerson()`: a registration with "Number of IDs" above 1 (public, member-assisted, store-assisted). ID 1 has
 *   the form's sponsor and place; IDs 2… are sponsored by ID 1 and fill the places under it level by level.
 * - `forExistingPerson()`: "Add ID" by the person or by company staff. Every new ID is sponsored by the person's first
 *   ID; the first new ID goes under the chosen parent (any member), the rest fill the places under it.
 * Each ID is made by `RegisterMember::createId()`, so it is exactly a normal pending registration. A one-ID
 * registration does not come here: it stays on `RegisterMember`, unchanged.
 */
class RegisterMemberIds
{
    private const MAX_PLACEMENT_RETRIES = 3;

    /** Member columns a 2nd ID copies from the person's first ID: the same person, so the same KYC details. */
    private const COPIED_PROFILE = ['gender', 'pan_card', 'aadhaar_card', 'profile_photo_path', 'address', 'pending_fields_submitted_at'];

    public function __construct(
        private readonly RegisterMember $registerMember,
        private readonly ValidateSponsorCode $validateSponsorCode,
        private readonly BinaryPlacementResolver $placementResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data  a validated registration: sponsor_code, placement_side, name, gender, email,
     *                                      mobile, membership_plan_id, payment_mode, id_count
     * @return Collection<int, Member> the new IDs, first one first
     */
    public function forNewPerson(array $data, ?User $createdBy = null): Collection
    {
        $count = (int) ($data['id_count'] ?? 1);
        $mode = (string) $data['payment_mode'];
        $this->guard($count, 0, $mode);

        return $this->withPlacementRetry(function () use ($data, $count, $mode, $createdBy): Collection {
            return DB::transaction(function () use ($data, $count, $mode, $createdBy): Collection {
                $sponsor = ($this->validateSponsorCode)((string) $data['sponsor_code']);
                $plan = $this->activePlan((int) $data['membership_plan_id']);
                $placement = $this->placementResolver->resolve($sponsor, $this->side($data['placement_side']));

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    'password' => Str::random(40), // Set to ID 1's Customer ID at activation, as for any registration.
                    'role' => 'member',
                ]);

                return $this->createIds($user, $sponsor, null, $placement, $plan, $mode, $count, ['gender' => $data['gender'] ?? null], $createdBy);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated "Add ID": id_count, membership_plan_id, parent_code, placement_side,
     *                                      payment_mode
     * @return Collection<int, Member> the new IDs, first one first
     */
    public function forExistingPerson(User $owner, array $data, User $createdBy): Collection
    {
        $count = (int) ($data['id_count'] ?? 1);
        $mode = (string) $data['payment_mode'];

        return $this->withPlacementRetry(function () use ($owner, $data, $count, $mode, $createdBy): Collection {
            return DB::transaction(function () use ($owner, $data, $count, $mode, $createdBy): Collection {
                // One request at a time per person, so two at once cannot pass the limit together.
                User::whereKey($owner->id)->lockForUpdate()->firstOrFail();

                $ids = Member::where('user_id', $owner->id)->orderBy('id')->get();
                $first = $ids->first();

                if ($first === null || $first->status !== 'active') {
                    throw ValidationException::withMessages(['id_count' => 'This person has no active ID to add to yet.']);
                }

                if ($ids->contains(fn (Member $id) => $id->isBlocked())) {
                    throw ValidationException::withMessages(['id_count' => Member::BLOCKED_LOGIN_MESSAGE]);
                }

                $this->guard($count, $ids->count(), $mode);

                $parent = Member::where('customer_id', trim((string) $data['parent_code']))->where('status', 'active')->first();

                if ($parent === null) {
                    throw ValidationException::withMessages(['parent_code' => 'No active member has this Customer ID.']);
                }

                $plan = $this->activePlan((int) $data['membership_plan_id']);
                $placement = $this->placementResolver->resolve($parent, $this->side($data['placement_side']));
                $profile = array_intersect_key($first->getAttributes(), array_flip(self::COPIED_PROFILE));

                $members = $this->createIds($owner, $first, $first, $placement, $plan, $mode, $count, $profile, $createdBy);

                $this->copyBankDetails($first, $members);

                return $members;
            });
        });
    }

    /**
     * @param  array{parent_id: int, side: string}  $firstPlacement
     * @param  array<string, mixed>  $profile
     * @return Collection<int, Member>
     */
    private function createIds(
        User $user,
        Member $firstSponsor,
        ?Member $laterSponsor,
        array $firstPlacement,
        MembershipPlan $plan,
        string $mode,
        int $count,
        array $profile,
        ?User $createdBy,
    ): Collection {
        $batch = $count > 1 ? PaymentBatch::create([
            'type' => 'registration',
            'mode' => $mode,
            'amount' => 0,
            'status' => 'pending',
            'cash_status' => PaymentModes::initialApprovalStatus($mode),
            'created_by' => $createdBy?->id,
        ]) : null;

        /** @var list<Member> $members */
        $members = [];

        for ($index = 0; $index < $count; $index++) {
            if ($index === 0) {
                $sponsor = $firstSponsor;
                $placement = $firstPlacement;
            } else {
                $slot = BinaryPlacementResolver::levelOrderSlot($index);
                $sponsor = $laterSponsor ?? $members[0];
                $placement = ['parent_id' => $members[$slot['parent_index']]->id, 'side' => $slot['side']];
            }

            $members[] = $this->registerMember->createId($user, $sponsor, $placement, $plan, $mode, $profile, $batch?->id);
        }

        $batch?->update(['amount' => $batch->payments()->sum('amount')]);

        DB::afterCommit(fn () => $this->announce($members[0]));

        return collect($members);
    }

    /** @param  Collection<int, Member>  $members */
    private function copyBankDetails(Member $first, Collection $members): void
    {
        $details = MemberBankDetail::where('member_id', $first->id)->get();

        foreach ($members as $member) {
            foreach ($details as $detail) {
                MemberBankDetail::create([
                    ...array_intersect_key($detail->getAttributes(), array_flip($detail->getFillable())),
                    'member_id' => $member->id,
                ]);
            }
        }
    }

    /** T-141 — one bell alert for the whole request: a combined payment is approved as one row. */
    private function announce(Member $first): void
    {
        $payment = $first->payments()->where('type', 'registration')->first();

        if ($payment !== null && PaymentModes::needsApproval($payment->mode)) {
            Notifier::toSuperAdmins(new CashPaymentAwaitingApproval($payment));
        }
    }

    private function guard(int $count, int $existing, string $mode): void
    {
        $limit = MemberIds::limit();

        if ($count < 1) {
            throw ValidationException::withMessages(['id_count' => 'Choose at least 1 ID.']);
        }

        if ($existing + $count > $limit) {
            $left = max(0, $limit - $existing);

            throw ValidationException::withMessages(['id_count' => $existing === 0
                ? "At most {$limit} IDs per person."
                : "At most {$limit} IDs per person. This person can add {$left} more."]);
        }

        if ($count > 1 && ! PaymentModes::needsApproval($mode)) {
            throw ValidationException::withMessages(['payment_mode' => 'Several IDs are paid together by Cash or GPay/UPI only.']);
        }
    }

    private function activePlan(int $planId): MembershipPlan
    {
        return MembershipPlan::where('id', $planId)->where('is_active', true)->firstOrFail();
    }

    /** @return 'left'|'right' */
    private function side(mixed $side): string
    {
        return match ($side) {
            'left' => 'left',
            'right' => 'right',
            default => throw ValidationException::withMessages(['placement_side' => 'Select Left or Right.']),
        };
    }

    /**
     * Same as `RegisterMember`: a concurrent registration may take the first ID's place between the walk and the
     * insert; the unique index refuses it, and the whole request is tried again.
     *
     * @param  callable(): Collection<int, Member>  $attempt
     * @return Collection<int, Member>
     */
    private function withPlacementRetry(callable $attempt): Collection
    {
        $tries = 0;

        while (true) {
            try {
                return $attempt();
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'members_placement_unique') || ++$tries >= self::MAX_PLACEMENT_RETRIES) {
                    throw $e;
                }
            }
        }
    }
}
