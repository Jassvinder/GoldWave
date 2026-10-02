<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberNetworkSummary;
use App\Support\NetworkNodeCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §4.1 — Directs View (Sponsor/Direct relationship only,
 * never Binary Position). One controller serves both a Member (own downline
 * only, MemberPolicy::viewDirects) and Super Admin (any member) — the
 * distinction is enforced by the Policy, not by separate routes/controllers.
 */
class DirectsController extends Controller
{
    public function show(Request $request, MemberNetworkSummary $network, ?Member $member = null): Response
    {
        $loggedInMember = $request->user()->member;

        $selected = $member ?? $loggedInMember;

        abort_if($selected === null, 404);
        $this->authorize('viewDirects', $selected);

        return Inertia::render('member/directs', [
            'loggedInMember' => $loggedInMember ? [
                'name' => $loggedInMember->user?->name,
                'customer_id' => $loggedInMember->customer_id,
            ] : null,
            'selectedMember' => NetworkNodeCard::from($selected),
            'team' => $network->teamCounts($selected),
            'directs' => $selected->directs()->with(['user', 'sponsor.user'])->get()->map(fn (Member $direct) => NetworkNodeCard::from($direct)),
        ]);
    }

    /**
     * Search by Customer ID within the viewer's own downline only (INSTRUCTIONS.md
     * M08 — search box; DOMAIN_LOGIC.md §4.1's authorization boundary applies
     * identically here, enforced by the same MemberPolicy::viewDirects check
     * `show()` uses — a Customer ID outside the viewer's downline never resolves,
     * "cross-leg" lookups are rejected the same as an unknown Customer ID).
     */
    public function search(Request $request): RedirectResponse
    {
        $request->validate(['customer_id' => ['required', 'string', 'max:20']]);

        $target = Member::where('customer_id', $request->string('customer_id')->toString())->first();

        if (! $target || $request->user()->cannot('viewDirects', $target)) {
            return back()->withErrors(['customer_id' => 'Customer ID not found in your downline.']);
        }

        return redirect()->route('member.directs.show', $target);
    }
}
