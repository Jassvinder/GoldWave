import { useRef, useState } from 'react';
import NetworkToolbar from '@/components/network-toolbar';

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    onSearch: (customerId: string) => void;
    searchError?: string;
    rootHref?: string | null;
    /** T-194 — team numbers shown between the toolbar and the diagram. */
    summary?: React.ReactNode;
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
    summary,
    children,
}: Props) {
    /*
     * Pan/zoom model: the diagram is transformed with origin 0 0, so a
     * viewport point p (relative to the viewport box) shows diagram point
     * (p - pan) / scale. One active pointer pans; two pointers (mobile
     * pinch) zoom around the fingers' midpoint and pan with it, keeping the
     * diagram point under the fingers fixed. Every pointer is tracked by id —
     * the old single-drag state treated a second finger as a fresh drag,
     * which made the diagram jump around while pinching.
     */
    type View = { scale: number; x: number; y: number };
    const [view, setViewState] = useState<View>({ scale: 1, x: 0, y: 0 });
    // Mirror of `view` readable synchronously inside pointer handlers.
    const viewRef = useRef<View>(view);

    function setView(next: View | ((v: View) => View)) {
        const v = typeof next === 'function' ? next(viewRef.current) : next;
        viewRef.current = v;
        setViewState(v);
    }
    const viewportRef = useRef<HTMLDivElement>(null);
    const pointers = useRef(new Map<number, { x: number; y: number }>());
    const gesture = useRef<
        | {
              kind: 'pan';
              startX: number;
              startY: number;
              originX: number;
              originY: number;
          }
        | {
              kind: 'pinch';
              startDist: number;
              startScale: number;
              anchorX: number;
              anchorY: number;
          }
        | null
    >(null);
    // Set once a gesture actually moves, so releasing a drag/pinch on top of
    // a member card does not also count as a click (re-rooting the tree).
    const moved = useRef(false);

    const MIN_SCALE = 0.2;
    const MAX_SCALE = 2;
    const clampScale = (s: number) =>
        Math.min(MAX_SCALE, Math.max(MIN_SCALE, s));

    function localPoint(clientX: number, clientY: number) {
        const rect = viewportRef.current?.getBoundingClientRect();
        return {
            x: clientX - (rect?.left ?? 0),
            y: clientY - (rect?.top ?? 0),
        };
    }

    function startGesture() {
        const pts = [...pointers.current.values()];
        const v = viewRef.current;

        if (pts.length >= 2) {
            const [a, b] = pts;
            const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
            gesture.current = {
                kind: 'pinch',
                startDist: Math.hypot(a.x - b.x, a.y - b.y) || 1,
                startScale: v.scale,
                anchorX: (mid.x - v.x) / v.scale,
                anchorY: (mid.y - v.y) / v.scale,
            };
        } else if (pts.length === 1) {
            gesture.current = {
                kind: 'pan',
                startX: pts[0].x,
                startY: pts[0].y,
                originX: v.x,
                originY: v.y,
            };
        } else {
            gesture.current = null;
        }
    }

    function onPointerDown(e: React.PointerEvent) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        if (pointers.current.size === 0) moved.current = false;
        pointers.current.set(e.pointerId, localPoint(e.clientX, e.clientY));
        (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
        startGesture();
    }

    function onPointerMove(e: React.PointerEvent) {
        if (!pointers.current.has(e.pointerId)) return;
        pointers.current.set(e.pointerId, localPoint(e.clientX, e.clientY));
        const g = gesture.current;
        if (!g) return;

        if (g.kind === 'pan') {
            const p = pointers.current.get(e.pointerId)!;
            const dx = p.x - g.startX;
            const dy = p.y - g.startY;
            if (Math.abs(dx) + Math.abs(dy) > 4) moved.current = true;
            setView((v) => ({ ...v, x: g.originX + dx, y: g.originY + dy }));
            return;
        }

        const [a, b] = [...pointers.current.values()];
        const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        const scale = clampScale(
            (g.startScale * Math.hypot(a.x - b.x, a.y - b.y)) / g.startDist,
        );
        moved.current = true;
        setView({
            scale,
            x: mid.x - g.anchorX * scale,
            y: mid.y - g.anchorY * scale,
        });
    }

    function onPointerEnd(e: React.PointerEvent) {
        if (!pointers.current.delete(e.pointerId)) return;
        // Re-baseline on the remaining finger(s) so lifting one finger of a
        // pinch does not make the diagram jump.
        startGesture();
    }

    function zoomBy(delta: number) {
        const rect = viewportRef.current?.getBoundingClientRect();
        const cx = (rect?.width ?? 0) / 2;
        const cy = (rect?.height ?? 0) / 2;
        setView((v) => {
            const scale = clampScale(v.scale + delta);
            return {
                scale,
                x: cx - ((cx - v.x) / v.scale) * scale,
                y: cy - ((cy - v.y) / v.scale) * scale,
            };
        });
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
                        onClick={() => zoomBy(-0.1)}
                        className="rounded-md border px-3 py-1 text-sm"
                    >
                        −
                    </button>
                    <button
                        type="button"
                        onClick={() => setView({ scale: 1, x: 0, y: 0 })}
                        className="rounded-md border px-3 py-1 text-sm"
                    >
                        Reset
                    </button>
                    <button
                        type="button"
                        onClick={() => zoomBy(0.1)}
                        className="rounded-md border px-3 py-1 text-sm"
                    >
                        +
                    </button>
                </div>
            </div>

            {summary}

            <div
                className="bg-muted/30 h-[70vh] cursor-grab touch-none overflow-hidden rounded-md border active:cursor-grabbing"
                ref={viewportRef}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerEnd}
                onPointerCancel={onPointerEnd}
                onPointerLeave={(e) => {
                    if (e.pointerType === 'mouse') onPointerEnd(e);
                }}
                onClickCapture={(e) => {
                    if (moved.current) {
                        e.preventDefault();
                        e.stopPropagation();
                        moved.current = false;
                    }
                }}
            >
                <div
                    className="flex h-full w-full items-start justify-center pt-10"
                    style={{
                        transform: `translate(${view.x}px, ${view.y}px) scale(${view.scale})`,
                        transformOrigin: '0 0',
                    }}
                >
                    {children}
                </div>
            </div>
        </div>
    );
}
