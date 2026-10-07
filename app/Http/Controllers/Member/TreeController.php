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
 * DOMAIN_LOGIC.md §4.2 — Tree View (Binary Position only, never
 * Sponsor/Direct). "Clicking any node makes it the Selected Member... that
 * member becomes the new Root Member with their own Left/Right branches"
 * (§4.2) is implemented as a full re-root navigation (a fresh page visit to
 * `/member/tree/{member}`) rather than a separate in-place-expand
 * interaction — clicking any visible node already shows that node's own
 * Left/Right branches once it becomes root, which satisfies "each child can
 * be expanded to view its own branches" without a second competing
 * interaction model. Loads 3 levels below the root (root + 3 generations =
 * up to 15 cards) — enough to show the recursive Left/Right structure
 * without pagination; zoom/pan is a frontend concern only.
 */
class TreeController extends Controller
{
    private const DEPTH = 3;

    public function show(Request $request, MemberNetworkSummary $network, ?Member $member = null): Response
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
            'root' => $this->buildTree($root, $network),
            'team' => $network->teamCounts($root),
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
     * T-208 (02-10-2026) — two passes: walk the visible levels first (one query per node for both children), then
     * build every card with its Left/Right counts from a single batched query.
     *
     * @return array<string, mixed>
     */
    private function buildTree(Member $root, MemberNetworkSummary $network): array
    {
        $members = [];
        $shape = $this->walk($root, self::DEPTH, $members);

        return $this->buildNode($shape, NetworkNodeCard::forMany($members, $network));
    }

    /**
     * @param  list<Member>  $members  collects every visited member
     * @return array{member: Member, left: array<string, mixed>|null, right: array<string, mixed>|null}
     */
    private function walk(Member $member, int $depth, array &$members): array
    {
        $members[] = $member;
        $shape = ['member' => $member, 'left' => null, 'right' => null];

        if ($depth <= 0) {
            return $shape;
        }

        $children = Member::with(['user', 'sponsor.user'])
            ->where('placement_parent_id', $member->id)
            ->whereIn('placement_side', ['left', 'right'])
            ->get()
            ->keyBy('placement_side');

        foreach (['left', 'right'] as $side) {
            $child = $children->get($side);
            $shape[$side] = $child ? $this->walk($child, $depth - 1, $members) : null;
        }

        return $shape;
    }

    /**
     * Recursive node shape: NetworkNodeCard fields + {left, right} where
     * left/right are either null or another node of this same shape — not
     * expressible as a finite PHPDoc array shape, hence the loose value type.
     *
     * @param  array<string, mixed>  $shape
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    private function buildNode(array $shape, array $cards): array
    {
        /** @var Member $member */
        $member = $shape['member'];

        return [
            ...$cards[$member->id],
            'left' => is_array($shape['left']) ? $this->buildNode($shape['left'], $cards) : null,
            'right' => is_array($shape['right']) ? $this->buildNode($shape['right'], $cards) : null,
        ];
    }
}
