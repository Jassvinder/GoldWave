import {
    TeamSummaryStrip,
    type TeamCounts,
} from '@/components/team-summary-strip';
import { Head, router, usePage } from '@inertiajs/react';
import { MemberNodeCard } from '@/components/member-node-card';
import type { NodeMember } from '@/components/member-node-card';
import NetworkDiagramShell from '@/components/network-diagram-shell';
import { search as searchTree, show as showTree } from '@/routes/member/tree';

type TreeNode = NodeMember & {
    left: TreeNode | null;
    right: TreeNode | null;
};

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    root: TreeNode;
    team: TeamCounts;
};

/**
 * DOMAIN_LOGIC.md §4.2 — Tree View. Binary Position only, never
 * Sponsor/Direct. Rectangular cards with a circular avatar (T-119).
 * Clicking any node re-roots the view at that member (a normal Inertia
 * visit to /member/tree/{id}) — see TreeController's docblock for why this
 * single interaction satisfies both "becomes the new Root" and "each child
 * can be expanded" from the spec. Zoom/pan is a simple CSS transform on the
 * whole diagram — no charting library needed for a 3-level (≤15 node) tree.
 */
export default function Tree({ loggedInMember, root, team }: Props) {
    const errors = usePage().props.errors as Record<string, string>;

    function handleSearch(customerId: string) {
        router.get(searchTree.url({ query: { customer_id: customerId } }));
    }

    return (
        <>
            <Head title="Tree View" />

            <NetworkDiagramShell
                loggedInMember={loggedInMember}
                onSearch={handleSearch}
                searchError={errors.customer_id}
                rootHref={loggedInMember ? showTree.url() : null}
                summary={<TeamSummaryStrip team={team} />}
            >
                <TreeBranch node={root} />
            </NetworkDiagramShell>
        </>
    );
}

/**
 * Connector geometry: a fixed-height vertical stub drops from the parent
 * card to a horizontal line spanning the Left and Right columns' centers,
 * then a vertical stub drops from each end of that line into the child (or
 * Empty placeholder). Each column draws only its own half of the horizontal
 * line (`left` column: center→right edge; `right` column: left edge→center)
 * — since the two columns sit flush against each other, the halves meet
 * exactly at the shared boundary, directly below the parent's center. This
 * stays correctly aligned regardless of how wide each child's own subtree
 * ends up being (unlike computing the line span from fixed pixel offsets).
 */
function TreeBranch({ node }: { node: TreeNode }) {
    const hasChildren = node.left !== null || node.right !== null;

    return (
        <div className="flex flex-col items-center">
            <MemberNodeCard member={node} href={showTree.url(node.id)} />

            {hasChildren && (
                <>
                    <div className="bg-border h-5 w-px" />
                    <div className="flex items-start">
                        <BranchColumn
                            label="Left"
                            side="left"
                            child={node.left}
                        />
                        <BranchColumn
                            label="Right"
                            side="right"
                            child={node.right}
                        />
                    </div>
                </>
            )}
        </div>
    );
}

function BranchColumn({
    label,
    side,
    child,
}: {
    label: string;
    side: 'left' | 'right';
    child: TreeNode | null;
}) {
    return (
        <div className="relative flex flex-col items-center px-3 pt-5">
            <div className="bg-border absolute top-0 left-1/2 h-5 w-px" />
            <div
                className={`border-border absolute top-0 h-px border-t ${side === 'left' ? 'right-0 left-1/2' : 'right-1/2 left-0'}`}
            />
            <span className="text-muted-foreground mb-1 text-xs">{label}</span>
            {child ? <TreeBranch node={child} /> : <EmptySlot />}
        </div>
    );
}

function EmptySlot() {
    return (
        <div className="text-muted-foreground flex h-[4.5rem] w-48 items-center justify-center rounded-md border border-dashed p-2 text-xs">
            Empty
        </div>
    );
}
