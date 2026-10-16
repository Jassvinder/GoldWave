<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Members\ApplyMemberRestriction;
use App\Actions\Members\LiftMemberRestriction;
use App\Actions\Members\RecordCashEmiForBlockedMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ChangeMemberRestrictionRequest;
use App\Models\Member;
use App\Models\MemberRestriction;
use App\Models\PayoutRequest;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — Super Admin's Member Restrictions page: find a member by Customer ID, then apply or
 * lift a Payout Hold or an Account Block (reason + typed Customer ID + password). Lists everyone restricted now and
 * the full history. Also records a cash EMI for a blocked member (from Member Detail).
 */
class MemberRestrictionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = strtoupper(trim($request->string('customer_id')->toString()));
        $found = $search === '' ? null : Member::with('user')->where('customer_id', $search)->first();

        $restricted = Member::with('user')
            ->where(fn ($query) => $query->whereNotNull('payout_hold_at')->orWhereNotNull('blocked_at'))
            ->orderByDesc('blocked_at')
            ->orderByDesc('payout_hold_at')
            ->get()
            ->map(fn (Member $member): array => $this->summary($member));

        $history = MemberRestriction::with(['member.user', 'startedBy', 'endedBy'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (MemberRestriction $row): array => [
                'id' => $row->id,
                'member_id' => $row->member_id,
                'customer_id' => $row->member->customer_id,
                'name' => $row->member->user?->name,
                'type' => $row->type,
                'reason' => $row->reason,
                'started_on' => Dates::date($row->started_at),
                'started_time' => $this->time($row->started_at),
                'started_by' => $row->startedBy->name,
                'ended_on' => Dates::date($row->ended_at),
                'ended_time' => $this->time($row->ended_at),
                'ended_by' => $row->endedBy?->name,
                'end_reason' => $row->end_reason,
            ]);

        return Inertia::render('super-admin/member-restrictions', [
            'search' => $search,
            'found' => $found !== null ? $this->summary($found) : null,
            'not_found' => $search !== '' && $found === null,
            'restricted' => $restricted,
            'history' => $history,
        ]);
    }

    public function apply(ChangeMemberRestrictionRequest $request, ApplyMemberRestriction $apply): RedirectResponse
    {
        $member = $request->member();
        $apply($member, $request->string('type')->toString(), $request->string('reason')->toString(), $request->user());

        return redirect()->route('super-admin.member-restrictions.index', ['customer_id' => $member->customer_id])
            ->with('status', $request->string('type')->toString() === MemberRestriction::ACCOUNT_BLOCK
                ? "{$member->customer_id} is blocked."
                : "{$member->customer_id} is on a Payout Hold.");
    }

    public function lift(ChangeMemberRestrictionRequest $request, LiftMemberRestriction $lift): RedirectResponse
    {
        $member = $request->member();
        $lift($member, $request->string('type')->toString(), $request->string('reason')->toString(), $request->user(), $request->boolean('also_lift_payout_hold'));

        return redirect()->route('super-admin.member-restrictions.index', ['customer_id' => $member->customer_id])
            ->with('status', $request->string('type')->toString() === MemberRestriction::ACCOUNT_BLOCK
                ? "{$member->customer_id} is unblocked."
                : "{$member->customer_id}'s Payout Hold is lifted.");
    }

    public function recordCashEmi(Member $member, Request $request, RecordCashEmiForBlockedMember $record): RedirectResponse
    {
        $payment = $record($member, $request->user());

        return back()->with('status', 'Cash EMI of ₹'.number_format((float) $payment->amount, 2).' recorded. It is settled in the background within a few seconds.');
    }

    /** @return array<string, mixed> */
    private function summary(Member $member): array
    {
        return [
            'id' => $member->id,
            'customer_id' => $member->customer_id,
            'name' => $member->placeholder_name ?? $member->user?->name,
            'status' => $member->status,
            'wallet_balance' => (string) $member->wallet_balance,
            'pending_payouts' => PayoutRequest::where('member_id', $member->id)->where('status', 'pending')->count(),
            'payout_hold_since' => Dates::date($member->payout_hold_at),
            'blocked_since' => Dates::date($member->blocked_at),
            'is_company_root' => (bool) $member->is_company_root,
        ];
    }

    private function time(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('h:i A') : null;
    }
}
