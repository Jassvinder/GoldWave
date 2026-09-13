<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §4.2 — Tree View (Binary Position only, never
 * Sponsor/Direct). "Clicking any node makes it the Selected Member... that
 * member becomes the new Root Member with their own Left/Right branches"
 * (§4.2) is implemented as a full re-root navigation (a fresh page visit to
 * `/member/tree/{member}`) rather than a separate in-place-expand
 * interaction — clicking any visible node already shows that node's own
 * Left/Right branches once it becomes root, which satisfies "each child can
 * be expanded to view its own branches" without a second competing
 * interaction model. Loads 2 levels below the root (root + children +
 * grandchildren = up to 7 cards) — enough to show the recursive Left/Right
 * structure without pagination; zoom/pan is a frontend concern only.
 */
class TreeController extends Controller
{
    private const DEPTH = 2;

    public function show(Request $request, ?Member $member = null): Response
    {
        $loggedInMember = $request->user()->member;

        $root = $member ?? $loggedInMember;

        abort_if($root === null, 404);
        $this->authorize('viewTree', $root);

        return Inertia::render('member/tree', [
            'loggedInMember' => $loggedInMember ? [
                'name' => $loggedInMember->user?->name,
                'customer_id' => $loggedInMember->customer_id,
            ] : null,
            'root' => $this->buildNode($root, self::DEPTH),
        ]);
    }

    /**
     * Search by Customer ID within the viewer's own placement downline only
     * (INSTRUCTIONS.md M09 — search box; same "cross-leg lookups rejected"
     * boundary as DirectsController::search, via MemberPolicy::viewTree).
     */
    public function search(Request $request): RedirectResponse
    {
        $request->validate(['customer_id' => ['required', 'string', 'max:20']]);

        $target = Member::where('customer_id', $request->string('customer_id')->toString())->first();

        if (! $target || $request->user()->cannot('viewTree', $target)) {
            return back()->withErrors(['customer_id' => 'Customer ID not found in your downline.']);
        }

        return redirect()->route('member.tree.show', $target);
    }

    /**
     * Recursive node shape: {id, name, customer_id, status, left, right} where
     * left/right are either null or another node of this same shape — not
     * expressible as a finite PHPDoc array shape, hence the loose value type.
     *
     * @return array<string, mixed>
     */
    private function buildNode(Member $member, int $depth): array
    {
        $member->loadMissing('user');

        $node = [
            'id' => $member->id,
            'name' => $member->user?->name,
            'customer_id' => $member->customer_id,
            'status' => $member->status,
            'left' => null,
            'right' => null,
        ];

        if ($depth <= 0) {
            return $node;
        }

        $left = Member::where('placement_parent_id', $member->id)->where('placement_side', 'left')->first();
        $right = Member::where('placement_parent_id', $member->id)->where('placement_side', 'right')->first();

        $node['left'] = $left ? $this->buildNode($left, $depth - 1) : null;
        $node['right'] = $right ? $this->buildNode($right, $depth - 1) : null;

        return $node;
    }
}
