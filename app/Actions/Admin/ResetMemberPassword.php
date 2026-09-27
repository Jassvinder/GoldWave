<?php

namespace App\Actions\Admin;

use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * T-111 (19-09-2026) — Super Admin can directly set a member's password, no
 * email/OTP reset flow involved (that flow, `Auth\SetNewPassword`, stays the
 * member's own self-service channel). Deliberately a separate Action/dialog
 * from `UpdateMemberDetails` (T-106) rather than folded into that form, so a
 * Super Admin editing an unrelated field never risks also submitting a
 * password change.
 */
class ResetMemberPassword
{
    public function __invoke(Member $member, string $newPassword): void
    {
        $user = $member->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'This member has no login account to reset.',
            ]);
        }

        $user->update(['password' => $newPassword]);
    }
}
