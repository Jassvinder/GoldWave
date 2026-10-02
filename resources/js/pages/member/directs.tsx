import {
    TeamSummaryStrip,
    type TeamCounts,
} from '@/components/team-summary-strip';
import { Head, router, usePage } from '@inertiajs/react';
import { MemberNodeCard } from '@/components/member-node-card';
import type { NodeMember } from '@/components/member-node-card';
import NetworkDiagramShell from '@/components/network-diagram-shell';
import {
    search as searchDirects,
    show as showDirects,
} from '@/routes/member/directs';

type Props = {
    loggedInMember: { name: string | null; customer_id: string | null } | null;
    selectedMember: NodeMember;
    directs: NodeMember[];
    team: TeamCounts;
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
    team,
}: Props) {
    const errors = usePage().props.errors as Record<string, string>;

    function handleSearch(customerId: string) {
        router.get(searchDirects.url({ query: { customer_id: customerId } }));
    }

    const rows: NodeMember[][] = [];
    for (let i = 0; i < directs.length; i += DIRECTS_PER_ROW) {
        rows.push(directs.slice(i, i + DIRECTS_PER_ROW));
    }

    return (
        <>
            <Head title="Directs View" />

            <NetworkDiagramShell
                loggedInMember={loggedInMember}
                onSearch={handleSearch}
                searchError={errors.customer_id}
                rootHref={loggedInMember ? showDirects.url() : null}
                summary={<TeamSummaryStrip team={team} />}
            >
                <div className="flex flex-col items-center">
                    <MemberNodeCard member={selectedMember} />

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
            </NetworkDiagramShell>
        </>
    );
}

function DirectsRow({ directs }: { directs: NodeMember[] }) {
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
                        <MemberNodeCard
                            member={direct}
                            href={showDirects.url(direct.id)}
                        />
                    </div>
                );
            })}
        </div>
    );
}
