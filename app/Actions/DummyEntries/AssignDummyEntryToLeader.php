<?php

namespace App\Actions\DummyEntries;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §14.3 — Super Admin enters a real leader's details into an
 * available dummy entry; the same `members` row (same id, Customer ID, and
 * tree position) becomes that leader's actual identity. Creates the
 * member's first `users` row (password seeded to the Customer ID, matching
 * `ActivateMembershipOnPaymentConfirmed`'s precedent, T-003) and activates
 * them directly. `placeholder_name` is preserved, not overwritten — §14.3's
 * "preserve full audit history" of the entry's dummy origin.
 *
 * T-149 (22-09-2026, user decision, supersedes the earlier "no plan/payment
 * required" reading) — the entry already carries an EMI plan and a paid
 * installment #1 from generation time (`GenerateDailyDummyEntries`); this is
 * the moment the leader's *own* schedule effectively begins — installment #2
 * onward is generated here, anchored to today regardless of how long the
 * entry sat unassigned (`GenerateLeaderEmiInstallments`).
 */
class AssignDummyEntryToLeader
{
    public function __construct(private readonly GenerateLeaderEmiInstallments $generateLeaderEmiInstallments) {}

    public function __invoke(Member $dummy, string $name, string $email, ?string $mobile, User $operator): Member
    {
        return DB::transaction(function () use ($dummy, $name, $email, $mobile, $operator) {
            $locked = Member::whereKey($dummy->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_company_dummy || $locked->dummy_status !== 'unassigned') {
                throw ValidationException::withMessages([
                    'dummy' => 'Only an unassigned dummy entry can be assigned to a leader.',
                ]);
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => $locked->customer_id,
                'role' => 'member',
            ]);

            $locked->update([
                'user_id' => $user->id,
                'status' => 'active',
                'dummy_status' => 'assigned',
                'dummy_assigned_at' => now(),
                'dummy_assigned_by' => $operator->id,
            ]);

            // T-149 — from here the leader pays installment #2 onward themselves; see that Action's docblock.
            ($this->generateLeaderEmiInstallments)($locked->fresh());

            return $locked->fresh();
        });
    }
}
