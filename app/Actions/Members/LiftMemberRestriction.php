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
 *
 * T-251 part 3 (§3.4) — lifted from every ID of the same person that has it, as it was applied.
 */
class LiftMemberRestriction
{
    public function __invoke(Member $member, string $type, string $reason, User $operator, bool $alsoLiftPayoutHold = false): void
    {
        DB::transaction(function () use ($member, $type, $reason, $operator, $alsoLiftPayoutHold) {
            $ids = PersonMembers::lock($member);
            $chosen = $ids->firstWhere('id', $member->id) ?? Member::whereKey($member->id)->firstOrFail();

            if ($chosen->{MemberRestriction::column($type)} === null) {
                throw ValidationException::withMessages([
                    'type' => $type === MemberRestriction::ACCOUNT_BLOCK ? 'This member is not blocked.' : 'This member is not on a Payout Hold.',
                ]);
            }

            $types = [$type];

            if ($type === MemberRestriction::ACCOUNT_BLOCK && $alsoLiftPayoutHold) {
                $types[] = MemberRestriction::PAYOUT_HOLD;
            }

            foreach ($ids as $locked) {
                foreach ($types as $lift) {
                    if ($locked->{MemberRestriction::column($lift)} === null) {
                        continue;
                    }

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
            }
        });

        $member->refresh();
    }
}
