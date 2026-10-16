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
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($locked->{$column} !== null) {
                throw ValidationException::withMessages([
                    'type' => $type === MemberRestriction::ACCOUNT_BLOCK ? 'This member is already blocked.' : 'This member is already on a Payout Hold.',
                ]);
            }

            $now = now();
            $locked->update([$column => $now]);

            foreach (PayoutRequest::where('member_id', $locked->id)->where('status', 'pending')->get() as $request) {
                ($this->cancelPayout)($request);
            }

            return MemberRestriction::create([
                'member_id' => $locked->id,
                'type' => $type,
                'reason' => trim($reason),
                'started_at' => $now,
                'started_by' => $operator->id,
            ]);
        });

        if ($type === MemberRestriction::ACCOUNT_BLOCK && $member->user_id !== null && config('session.driver') === 'database') {
            // Open sessions end at once; `EnsureMemberNotBlocked` also signs out any other session on its next request.
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $member->user_id)->delete();
        }

        $member->refresh();

        return $restriction;
    }
}
