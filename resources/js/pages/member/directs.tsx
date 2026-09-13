import { Head, Link, router, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import NetworkToolbar from '@/components/network-toolbar';
import {
    search as searchDirects,
    show as showDirects,
} from '@/routes/member/directs';

type MemberSummary = {
    id: number;
    name: string | null;
    customer_id: string | null;
    status: string;
};

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    selectedMember: MemberSummary;
    directs: MemberSummary[];
};

const DIRECTS_PER_ROW = 4;

/**
 * DOMAIN_LOGIC.md §4.1 — Directs View. Sponsor/Direct relationship only,
 * never Binary Position. Rendered as a tree diagram (same connector-line
 * technique as Tree View, generalized to N siblings instead of a fixed
 * Left/Right pair): one vertical stub below the Selected Member, one
 * horizontal trunk spanning all directs, one vertical stub into each direct.
 * When there are more directs than fit one row, additional rows wrap below,
 * each connected by a stub dropping from the row above's own center — not a
 * fixed pagination limit, every direct is reachable by scrolling down.
 * Clicking any direct re-selects it (a real Inertia visit, so browser
 * Back — see NetworkToolbar — retraces the exact click path); search is
 * scoped server-side to the viewer's own downline
 * (DirectsController::search).
 */
export default function Directs({
    loggedInMember,
    selectedMember,
    directs,
}: Props) {
    const errors = usePage().props.errors as Record<string, string>;

    function handleSearch(customerId: string) {
        router.get(searchDirects.url({ query: { customer_id: customerId } }));
    }

    const rows: MemberSummary[][] = [];
    for (let i = 0; i < directs.length; i += DIRECTS_PER_ROW) {
        rows.push(directs.slice(i, i + DIRECTS_PER_ROW));
    }

    return (
        <>
            <Head title="Directs View" />

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
                </div>

                <div className="flex flex-col items-center overflow-x-auto pb-6">
                    <SelectedCard member={selectedMember} />

                    {directs.length > 0 && (
                        <div className="flex flex-col items-center">
                            {rows.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex flex-col items-center"
                                >
                                    <div className="bg-border h-5 w-px" />
                                    <DirectsRow directs={row} />
                                </div>
                            ))}
                        </div>
                    )}

                    {directs.length === 0 && (
                        <p className="text-muted-foreground mt-4 text-sm">
                            No direct members yet.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

function SelectedCard({ member }: { member: MemberSummary }) {
    return (
        <div className="bg-card w-40 rounded-md border p-2 text-center text-xs shadow-sm">
            <div className="truncate font-medium">
                {member.name ?? 'Unnamed member'}
            </div>
            <div className="text-muted-foreground truncate">
                {member.customer_id ?? '—'}
            </div>
            <Badge
                variant={member.status === 'active' ? 'default' : 'secondary'}
                className="mt-1"
            >
                {member.status}
            </Badge>
        </div>
    );
}

function DirectsRow({ directs }: { directs: MemberSummary[] }) {
    const onlyOne = directs.length === 1;

    return (
        <div className="flex items-start">
            {directs.map((direct, index) => {
                const position =
                    index === 0
                        ? 'first'
                        : index === directs.length - 1
                          ? 'last'
                          : 'middle';

                return (
                    <div
                        key={direct.id}
                        className="relative flex flex-col items-center px-4 pt-5"
                    >
                        {!onlyOne && (
                            <div
                                className={`border-border absolute top-0 h-px border-t ${
                                    position === 'first'
                                        ? 'right-0 left-1/2'
                                        : position === 'last'
                                          ? 'right-1/2 left-0'
                                          : 'right-0 left-0'
                                }`}
                            />
                        )}
                        <div className="bg-border absolute top-0 left-1/2 h-5 w-px" />
                        <DirectCard direct={direct} />
                    </div>
                );
            })}
        </div>
    );
}

function DirectCard({ direct }: { direct: MemberSummary }) {
    return (
        <Link
            href={showDirects.url(direct.id)}
            className="bg-card hover:border-primary block w-36 rounded-md border p-2 text-center text-xs shadow-sm transition-colors"
        >
            <div className="truncate font-medium">
                {direct.name ?? 'Unnamed member'}
            </div>
            <div className="text-muted-foreground truncate">
                {direct.customer_id ?? '—'}
            </div>
            <Badge
                variant={direct.status === 'active' ? 'default' : 'secondary'}
                className="mt-1"
            >
                {direct.status}
            </Badge>
        </Link>
    );
}
