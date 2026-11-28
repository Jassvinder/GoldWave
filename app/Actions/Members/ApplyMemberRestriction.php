<?php

namespace App\Actions\Members;

use App\Actions\Payout\CancelPayoutRequest;
use App\Models\Member;
use App\Models\MemberRestriction;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — Super Admin puts a member under a Payout Hold or an Account Block, with a reason.
 * Takes effect now; nothing before it is undone. Either one cancels every pending payout request of the member and
 * releases its wallet hold. A block also signs the member out of every open session.
 *
 * T-251 part 3 (§3.4) — a restriction is for the person: it is applied to every ID of the same user at once (each gets
 * its own history row with the same reason). An ID that already has it keeps its own.
 */
class ApplyMemberRestriction
{
    public function __construct(private readonly CancelPayoutRequest $cancelPayout) {}

    public function __invoke(Member $member, string $type, string $reason, User $operator): MemberRestriction
    {
        $column = MemberRestriction::column($type);

        if ($member->is_company_root || ($member->is_company_dummy && $member->dummy_status !== 'assigned')) {
            throw ValidationException::withMessages(['member_id' => 'The company root and unassigned company entries never earn, so they are not restricted.']);
        }

        $restriction = DB::transaction(function () use ($member, $type, $reason, $operator, $column) {
            $ids = PersonMembers::lock($member);
            $chosen = $ids->firstWhere('id', $member->id) ?? Member::whereKey($member->id)->firstOrFail();

            if ($chosen->{$column} !== null) {
                throw ValidationException::withMessages([
                    'type' => $type === MemberRestriction::ACCOUNT_BLOCK ? 'This member is already blocked.' : 'This member is already on a Payout Hold.',
                ]);
            }

            $now = now();
            $created = null;

            foreach ($ids->filter(fn (Member $id) => $id->{$column} === null) as $locked) {
                $locked->update([$column => $now]);

                foreach (PayoutRequest::where('member_id', $locked->id)->where('status', 'pending')->get() as $request) {
                    ($this->cancelPayout)($request);
                }

                $row = MemberRestriction::create([
                    'member_id' => $locked->id,
                    'type' => $type,
                    'reason' => trim($reason),
                    'started_at' => $now,
                    'started_by' => $operator->id,
                ]);

                if ($locked->id === $member->id) {
                    $created = $row;
                }
            }

            return $created ?? throw new \LogicException('The chosen member was not restricted.');
        });

        if ($type === MemberRestriction::ACCOUNT_BLOCK && $member->user_id !== null && config('session.driver') === 'database') {
            // Open sessions end at once; `EnsureMemberNotBlocked` also signs out any other session on its next request.
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $member->user_id)->delete();
        }

        $member->refresh();

        return $restriction;
    }
}
