<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Models\MemberRestriction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — Super Admin lifts a Payout Hold or an Account Block, with a reason; everything it
 * stopped resumes from now, and nothing from the restricted period is paid. Lifting a block can also lift a separate
 * Payout Hold in the same step (the Unblock dialog's "Also lift the Payout Hold").
 */
class LiftMemberRestriction
{
    public function __invoke(Member $member, string $type, string $reason, User $operator, bool $alsoLiftPayoutHold = false): void
    {
        DB::transaction(function () use ($member, $type, $reason, $operator, $alsoLiftPayoutHold) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($locked->{MemberRestriction::column($type)} === null) {
                throw ValidationException::withMessages([
                    'type' => $type === MemberRestriction::ACCOUNT_BLOCK ? 'This member is not blocked.' : 'This member is not on a Payout Hold.',
                ]);
            }

            $types = [$type];

            if ($type === MemberRestriction::ACCOUNT_BLOCK && $alsoLiftPayoutHold && $locked->payout_hold_at !== null) {
                $types[] = MemberRestriction::PAYOUT_HOLD;
            }

            foreach ($types as $lift) {
                $locked->update([MemberRestriction::column($lift) => null]);

                MemberRestriction::where('member_id', $locked->id)
                    ->where('type', $lift)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => now(),
                        'ended_by' => $operator->id,
                        'end_reason' => trim($reason),
                    ]);
            }
        });

        $member->refresh();
    }
}
