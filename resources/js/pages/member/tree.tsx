import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import NetworkToolbar from '@/components/network-toolbar';
import { search as searchTree, show as showTree } from '@/routes/member/tree';

type TreeNode = {
    id: number;
    name: string | null;
    customer_id: string | null;
    status: string;
    left: TreeNode | null;
    right: TreeNode | null;
};

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    root: TreeNode;
};

/**
 * DOMAIN_LOGIC.md §4.2 — Tree View. Binary Position only, never
 * Sponsor/Direct. Rectangular cards only (no circular node designs).
 * Clicking any node re-roots the view at that member (a normal Inertia
 * visit to /member/tree/{id}) — see TreeController's docblock for why this
 * single interaction satisfies both "becomes the new Root" and "each child
 * can be expanded" from the spec. Zoom/pan is a simple CSS transform on the
 * whole diagram — no charting library needed for a 2-level (≤7 node) tree.
 */
export default function Tree({ loggedInMember, root }: Props) {
    const [scale, setScale] = useState(1);
    const [pan, setPan] = useState({ x: 0, y: 0 });
    const dragState = useRef<{
        startX: number;
        startY: number;
        originX: number;
        originY: number;
    } | null>(null);

    function onPointerDown(e: React.PointerEvent) {
        dragState.current = {
            startX: e.clientX,
            startY: e.clientY,
            originX: pan.x,
            originY: pan.y,
        };
        (e.target as HTMLElement).setPointerCapture(e.pointerId);
    }

    function onPointerMove(e: React.PointerEvent) {
        if (!dragState.current) return;
        const dx = e.clientX - dragState.current.startX;
        const dy = e.clientY - dragState.current.startY;
        setPan({
            x: dragState.current.originX + dx,
            y: dragState.current.originY + dy,
        });
    }

    function onPointerUp() {
        dragState.current = null;
    }

    const errors = usePage().props.errors as Record<string, string>;

    function handleSearch(customerId: string) {
        router.get(searchTree.url({ query: { customer_id: customerId } }));
    }

    return (
        <>
            <Head title="Tree View" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Logged in as
                        </p>
                        <p className="text-lg font-semibold">
                            {loggedInMember?.name ?? '—'}{' '}
                            <span className="text-muted-foreground font-normal">
                                ({loggedInMember?.customer_id ?? '—'})
                            </span>
                        </p>
                    </div>
                    <NetworkToolbar
                        onSearch={handleSearch}
                        error={errors.customer_id}
                    />
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() =>
                                setScale((s) => Math.max(0.5, s - 0.1))
                            }
                            className="rounded-md border px-3 py-1 text-sm"
                        >
                            −
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setScale(1);
                                setPan({ x: 0, y: 0 });
                            }}
                            className="rounded-md border px-3 py-1 text-sm"
                        >
                            Reset
                        </button>
                        <button
                            type="button"
                            onClick={() =>
                                setScale((s) => Math.min(2, s + 0.1))
                            }
                            className="rounded-md border px-3 py-1 text-sm"
                        >
                            +
                        </button>
                    </div>
                </div>

                <div
                    className="bg-muted/30 h-[70vh] cursor-grab touch-none overflow-hidden rounded-md border active:cursor-grabbing"
                    onPointerDown={onPointerDown}
                    onPointerMove={onPointerMove}
                    onPointerUp={onPointerUp}
                    onPointerLeave={onPointerUp}
                >
                    <div
                        className="flex h-full w-full items-start justify-center pt-10"
                        style={{
                            transform: `translate(${pan.x}px, ${pan.y}px) scale(${scale})`,
                            transformOrigin: 'top center',
                        }}
                    >
                        <TreeBranch node={root} />
                    </div>
                </div>
            </div>
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
            <NodeCard node={node} />

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
        <div className="relative flex flex-col items-center px-6 pt-5">
            <div className="bg-border absolute top-0 left-1/2 h-5 w-px" />
            <div
                className={`border-border absolute top-0 h-px border-t ${side === 'left' ? 'right-0 left-1/2' : 'right-1/2 left-0'}`}
            />
            <span className="text-muted-foreground mb-1 text-xs">{label}</span>
            {child ? <TreeBranch node={child} /> : <EmptySlot />}
        </div>
    );
}

function NodeCard({ node }: { node: TreeNode }) {
    return (
        <Link
            href={showTree.url(node.id)}
            className="bg-card hover:border-primary block w-36 rounded-md border p-2 text-center text-xs shadow-sm transition-colors"
        >
            <div className="truncate font-medium">{node.name ?? 'Unnamed'}</div>
            <div className="text-muted-foreground truncate">
                {node.customer_id ?? '—'}
            </div>
            <Badge
                variant={node.status === 'active' ? 'default' : 'secondary'}
                className="mt-1"
            >
                {node.status}
            </Badge>
        </Link>
    );
}

function EmptySlot() {
    return (
        <div className="text-muted-foreground flex w-36 items-center justify-center rounded-md border border-dashed p-2 text-xs">
            Empty
        </div>
    );
}
