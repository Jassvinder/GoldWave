<?php

namespace App\Support;

use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Registration\RegisterMember;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Earnings-testing helper shared by `test:entries`, `test:entries-file` and `test:entries-bulk` (13-10-2026).
 * Registers one member through the real flow (RegisterMember + cash approval), so every income runs exactly as for
 * a real joining, then names it after the Customer ID it actually received ("Test Member 17" = GWL17) unless a name
 * is given. The commands refuse to run in production.
 */
class TestEntryRegistrar
{
    public function __construct(
        private readonly RegisterMember $register,
        private readonly ApproveCashPayment $approve,
    ) {}

    public function planId(string $code): ?int
    {
        return MembershipPlan::where('code', strtoupper($code))->value('id');
    }

    public function approver(): ?User
    {
        return User::where('role', 'super_admin')->first();
    }

    /** True when the code belongs to an active member, the only kind RegisterMember accepts as a sponsor. */
    public function isActiveSponsor(string $customerId): bool
    {
        return Member::where('customer_id', $customerId)->where('status', 'active')->exists();
    }

    public function register(string $sponsor, string $side, int $planId, User $approver, ?string $name = null): Member
    {
        return DB::transaction(function () use ($sponsor, $side, $planId, $approver, $name): Member {
            $token = uniqid();
            $member = ($this->register)([
                'sponsor_code' => $sponsor,
                'placement_side' => $side,
                'gender' => 'male',
                'name' => 'Test Member',
                'email' => "test-{$token}@goldwave.test",
                'mobile' => '97'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                'membership_plan_id' => $planId,
                'payment_mode' => 'cash',
            ]);

            ($this->approve)($member->payments()->where('type', 'registration')->firstOrFail(), $approver);

            // The Customer ID is issued on approval — name and email follow the ID the member actually received.
            $member = $member->fresh(['user', 'placementParent']) ?? $member;
            $number = self::numberOf((string) $member->customer_id);
            $member->user?->update([
                'name' => $name !== null && $name !== '' ? $name : "Test Member {$number}",
                'email' => "member{$number}-{$token}@goldwave.test",
            ]);

            return $member;
        });
    }

    /** "GWL17" → "17", "GWL01" → "1" (the existing "Test Member 1" style). */
    public static function numberOf(string $customerId): string
    {
        $digits = preg_replace('/\D/', '', $customerId);

        return $digits === '' || $digits === null ? $customerId : (string) (int) $digits;
    }
}
