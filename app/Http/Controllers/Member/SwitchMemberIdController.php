<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\MemberIds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — the header "Switch ID": the person picks which of their own active IDs every member
 * page shows. Always lands on the dashboard, because the page they were on may belong to the previous ID.
 */
class SwitchMemberIdController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['member_id' => ['required', 'integer']]);

        $member = Member::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereKey((int) $validated['member_id'])
            ->first();

        if ($member === null) {
            throw ValidationException::withMessages(['member_id' => 'This ID is not one of your active IDs.']);
        }

        MemberIds::select($member);

        return redirect()->route('dashboard')->with('status', "Switched to {$member->customer_id}.");
    }
}
