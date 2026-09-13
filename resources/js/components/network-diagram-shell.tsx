import { useRef, useState } from 'react';
import NetworkToolbar from '@/components/network-toolbar';

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    onSearch: (customerId: string) => void;
    searchError?: string;
    children: React.ReactNode;
};

/**
 * Shared chrome for Directs View and Tree View (DOMAIN_LOGIC.md §4.1/§4.2) —
 * the user explicitly asked for identical UI (pan/zoom viewport, Back,
 * Search, zoom controls) between the two, differing only in the diagram
 * content passed as `children`. Kept as one component specifically so the
 * two pages cannot drift apart from each other by accident.
 *
 * Each toolbar concern (navigation, search, zoom) gets its own bordered/
 * tinted box per the user's explicit request for visually separated
 * sections, rather than one undifferentiated row of controls.
 */
export default function NetworkDiagramShell({
    loggedInMember,
    onSearch,
    searchError,
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
            <div className="flex flex-wrap items-stretch gap-3">
                <div className="bg-muted/30 rounded-md border border-l-4 border-l-slate-400 px-3 py-2">
                    <p className="text-muted-foreground text-xs">
                        Logged in as
                    </p>
                    <p className="text-sm font-semibold">
                        {loggedInMember?.name ?? '—'}{' '}
                        <span className="text-muted-foreground font-normal">
                            ({loggedInMember?.customer_id ?? '—'})
                        </span>
                    </p>
                </div>

                <div className="bg-muted/30 rounded-md border border-l-4 border-l-blue-400 px-3 py-2">
                    <NetworkToolbar onSearch={onSearch} error={searchError} />
                </div>

                <div className="bg-muted/30 flex items-center gap-2 rounded-md border border-l-4 border-l-emerald-400 px-3 py-2">
                    <button
                        type="button"
                        onClick={() => setScale((s) => Math.max(0.5, s - 0.1))}
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
