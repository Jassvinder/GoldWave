import { useRef, useState } from 'react';
import NetworkToolbar from '@/components/network-toolbar';

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    onSearch: (customerId: string) => void;
    searchError?: string;
    rootHref?: string | null;
    children: React.ReactNode;
};

/**
 * Shared chrome for Directs View and Tree View (DOMAIN_LOGIC.md §4.1/§4.2) —
 * the user explicitly asked (T-118) for the Directs page to have the exact
 * same UI as Tree View (header, Back/Search, zoom/pan viewport), differing
 * only in the member cards/diagram passed as `children`. Kept as one
 * component so the two pages cannot drift apart from each other by accident.
 */
export default function NetworkDiagramShell({
    loggedInMember,
    onSearch,
    searchError,
    rootHref,
    children,
}: Props) {
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

    return (
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
                    onSearch={onSearch}
                    error={searchError}
                    rootHref={rootHref}
                />

                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setScale((s) => Math.max(0.2, s - 0.1))}
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
                        onClick={() => setScale((s) => Math.min(2, s + 0.1))}
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
                    {children}
                </div>
            </div>
        </div>
    );
}
