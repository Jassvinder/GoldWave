import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Ban, History, Search, ShieldBan, ShieldCheck } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/utils';
import { apply, index, lift } from '@/routes/super-admin/member-restrictions';
import { show as showMember } from '@/routes/super-admin/members';

type MemberSummary = {
    id: number;
    customer_id: string;
    name: string | null;
    status: string;
    wallet_balance: string;
    pending_payouts: number;
    payout_hold_since: string | null;
    blocked_since: string | null;
    is_company_root: boolean;
};

type HistoryRow = {
    id: number;
    member_id: number;
    customer_id: string;
    name: string | null;
    type: 'payout_hold' | 'account_block';
    reason: string;
    started_on: string | null;
    started_time: string | null;
    started_by: string;
    ended_on: string | null;
    ended_time: string | null;
    ended_by: string | null;
    end_reason: string | null;
};

type Props = {
    search: string;
    found: MemberSummary | null;
    not_found: boolean;
    restricted: MemberSummary[];
    history: HistoryRow[];
};

type Kind = 'payout_hold' | 'account_block';

const TYPE_LABEL: Record<Kind, string> = {
    payout_hold: 'Payout Hold',
    account_block: 'Account Block',
};

const rupees = (value: string) => `₹${Number(value).toLocaleString('en-IN')}`;

/** What applying or lifting each restriction does — shown in the dialog so nobody acts without knowing. */
const EFFECTS: Record<Kind, Record<'apply' | 'lift', string[]>> = {
    payout_hold: {
        apply: [
            'The member can still log in, and earnings keep reaching the wallet.',
            'New payout requests and wallet-paid registrations are refused.',
            'Every pending payout request is cancelled now, and its amount is released back to the wallet.',
        ],
        lift: ['The member can request payouts again from now.'],
    },
    account_block: {
        apply: [
            'The member is signed out and can no longer log in.',
            'All of their earnings stop from now and are never paid later. The wallet balance stays as it is.',
            'Every pending payout request is cancelled now. They are not grouped or drawn in the Monthly Draw.',
            'They stay in the tree; their uplines are not affected. EMIs can still be paid (Record Cash EMI on the member page).',
        ],
        lift: [
            'The member can log in again, and earnings resume from now.',
            'Nothing from the blocked period is paid.',
        ],
    },
};

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — one deliberate step to apply or lift a restriction: what it does, a reason, the
 * member's Customer ID typed again and the Super Admin's password. Lifting a block can also lift a separate hold.
 */
function RestrictionDialog({
    member,
    type,
    mode,
}: {
    member: MemberSummary;
    type: Kind;
    mode: 'apply' | 'lift';
}) {
    const [open, setOpen] = useState(false);
    const canAlsoLiftHold =
        mode === 'lift' &&
        type === 'account_block' &&
        member.payout_hold_since !== null;
    const form = useForm({
        member_id: member.id,
        type,
        reason: '',
        confirm_customer_id: '',
        password: '',
        also_lift_payout_hold: true,
    });

    const title =
        mode === 'apply'
            ? type === 'account_block'
                ? 'Block account'
                : 'Put on Payout Hold'
            : type === 'account_block'
              ? 'Unblock account'
              : 'Lift Payout Hold';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(mode === 'apply' ? apply.url() : lift.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    const destructive = mode === 'apply' && type === 'account_block';

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant={
                        destructive
                            ? 'destructive'
                            : mode === 'apply'
                              ? 'outline'
                              : 'secondary'
                    }
                >
                    {title}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {title} — {member.customer_id}
                    </DialogTitle>
                    <DialogDescription>
                        {member.name ?? 'Unknown member'} · Wallet{' '}
                        {rupees(member.wallet_balance)}
                    </DialogDescription>
                </DialogHeader>

                <ul className="bg-muted/40 list-disc space-y-1 rounded-md py-2 pr-3 pl-7 text-sm">
                    {EFFECTS[type][mode].map((line) => (
                        <li key={line}>{line}</li>
                    ))}
                    {mode === 'apply' && member.pending_payouts > 0 && (
                        <li className="font-medium">
                            {member.pending_payouts} pending payout request
                            {member.pending_payouts === 1 ? '' : 's'} will be
                            cancelled.
                        </li>
                    )}
                </ul>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`reason-${type}-${mode}`}>
                            Reason (kept in the history)
                        </Label>
                        <textarea
                            id={`reason-${type}-${mode}`}
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            placeholder={
                                mode === 'apply'
                                    ? 'e.g. Duplicate accounts found — under review'
                                    : 'e.g. Review complete, documents verified'
                            }
                            required
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    {canAlsoLiftHold && (
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="accent-primary mt-0.5 size-4"
                                checked={form.data.also_lift_payout_hold}
                                onChange={(e) =>
                                    form.setData(
                                        'also_lift_payout_hold',
                                        e.target.checked,
                                    )
                                }
                            />
                            <span>
                                Also lift the Payout Hold (on since{' '}
                                {formatDate(member.payout_hold_since)}). Untick
                                to keep withdrawals on hold.
                            </span>
                        </label>
                    )}

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor={`confirm-${type}-${mode}`}>
                                Type {member.customer_id} to confirm
                            </Label>
                            <Input
                                id={`confirm-${type}-${mode}`}
                                autoComplete="off"
                                value={form.data.confirm_customer_id}
                                onChange={(e) =>
                                    form.setData(
                                        'confirm_customer_id',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={form.errors.confirm_customer_id}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`password-${type}-${mode}`}>
                                Your password
                            </Label>
                            <Input
                                id={`password-${type}-${mode}`}
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.password} />
                        </div>
                    </div>

                    <InputError
                        message={form.errors.type ?? form.errors.member_id}
                    />

                    <DialogFooter>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={
                                form.processing ||
                                form.data.reason.trim().length < 10 ||
                                form.data.confirm_customer_id
                                    .trim()
                                    .toUpperCase() !== member.customer_id
                            }
                        >
                            {title}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function StateBadges({ member }: { member: MemberSummary }) {
    if (!member.payout_hold_since && !member.blocked_since) {
        return <Badge variant="success">No restriction</Badge>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {member.blocked_since && (
                <Badge variant="destructive">
                    Blocked since {formatDate(member.blocked_since)}
                </Badge>
            )}
            {member.payout_hold_since && (
                <Badge className="border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-300">
                    Payout Hold since {formatDate(member.payout_hold_since)}
                </Badge>
            )}
        </div>
    );
}

const historyColumns: DataTableColumn<HistoryRow>[] = [
    {
        key: 'started',
        header: 'Started',
        render: (row) => (
            <div className="whitespace-nowrap">
                <div className="font-medium">{formatDate(row.started_on)}</div>
                <div className="text-muted-foreground text-xs">
                    {row.started_time} · {row.started_by}
                </div>
            </div>
        ),
    },
    {
        key: 'member',
        header: 'Member',
        render: (row) => (
            <div>
                <div className="font-medium">{row.customer_id}</div>
                <div className="text-muted-foreground text-xs">
                    {row.name ?? '—'}
                </div>
            </div>
        ),
    },
    {
        key: 'type',
        header: 'Restriction',
        render: (row) => (
            <Badge
                variant={
                    row.type === 'account_block' ? 'destructive' : 'secondary'
                }
            >
                {TYPE_LABEL[row.type]}
            </Badge>
        ),
    },
    {
        key: 'reason',
        header: 'Reason',
        render: (row) => (
            <span className="block max-w-xs text-sm break-words whitespace-normal">
                {row.reason}
            </span>
        ),
    },
    {
        key: 'ended',
        header: 'Ended',
        render: (row) =>
            row.ended_on ? (
                <div className="max-w-xs">
                    <div className="font-medium whitespace-nowrap">
                        {formatDate(row.ended_on)}
                    </div>
                    <div className="text-muted-foreground text-xs">
                        {row.ended_time} · {row.ended_by}
                    </div>
                    {row.end_reason && (
                        <div className="text-xs break-words whitespace-normal">
                            {row.end_reason}
                        </div>
                    )}
                </div>
            ) : (
                <Badge variant="outline">In force</Badge>
            ),
    },
];

/**
 * T-231 (DOMAIN_LOGIC.md §2.3) — Super Admin's Member Restrictions page: Payout Hold (withdrawals stop) and Account
 * Block (login and earnings stop). Find a member, apply or lift with a reason, see who is restricted now and the
 * full history.
 */
export default function MemberRestrictions({
    search,
    found,
    not_found,
    restricted,
    history,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [customerId, setCustomerId] = useState(search);

    const find: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            index.url(),
            customerId.trim() ? { customer_id: customerId.trim() } : {},
            { preserveScroll: true },
        );
    };

    const restrictedColumns: DataTableColumn<MemberSummary>[] = [
        {
            key: 'member',
            header: 'Member',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.customer_id}</div>
                    <div className="text-muted-foreground text-xs">
                        {row.name ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'state',
            header: 'Restriction',
            render: (row) => <StateBadges member={row} />,
        },
        {
            key: 'wallet',
            header: 'Wallet',
            render: (row) => rupees(row.wallet_balance),
        },
    ];

    return (
        <>
            <Head title="Member Restrictions" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={ShieldBan}
                    color="red"
                    title="Member Restrictions"
                    description="Payout Hold stops withdrawals only. Account Block stops login and all earnings from that moment. Both need a reason, the Customer ID typed again and your password."
                    contentClassName="flex flex-col gap-4"
                >
                    <form
                        onSubmit={find}
                        className="flex max-w-md items-end gap-2"
                    >
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="find-customer-id">
                                Customer ID
                            </Label>
                            <Input
                                id="find-customer-id"
                                placeholder="e.g. GWL01234"
                                value={customerId}
                                onChange={(e) => setCustomerId(e.target.value)}
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            <Search />
                            Find
                        </Button>
                    </form>

                    {not_found && (
                        <p className="text-destructive text-sm">
                            No member with Customer ID {search}.
                        </p>
                    )}

                    {found && (
                        <div className="flex flex-col gap-3 rounded-md border p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="text-lg font-semibold">
                                        {found.customer_id} —{' '}
                                        {found.name ?? 'Unknown member'}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        Status {found.status} · Wallet{' '}
                                        {rupees(found.wallet_balance)} ·{' '}
                                        {found.pending_payouts} pending payout
                                        request
                                        {found.pending_payouts === 1 ? '' : 's'}
                                    </div>
                                </div>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={showMember.url(found.id)}>
                                        View member
                                    </Link>
                                </Button>
                            </div>

                            <StateBadges member={found} />

                            {found.is_company_root ? (
                                <p className="text-muted-foreground text-sm">
                                    The company root never earns, so it is not
                                    restricted.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    <RestrictionDialog
                                        key={`hold-${found.payout_hold_since ?? 'none'}`}
                                        member={found}
                                        type="payout_hold"
                                        mode={
                                            found.payout_hold_since
                                                ? 'lift'
                                                : 'apply'
                                        }
                                    />
                                    <RestrictionDialog
                                        key={`block-${found.blocked_since ?? 'none'}-${found.payout_hold_since ?? 'none'}`}
                                        member={found}
                                        type="account_block"
                                        mode={
                                            found.blocked_since
                                                ? 'lift'
                                                : 'apply'
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </FormSection>

                <FormSection
                    icon={Ban}
                    color="amber"
                    title={`Restricted now (${restricted.length})`}
                    description="Open a member to lift or change their restriction."
                >
                    <DataTable
                        columns={restrictedColumns}
                        rows={restricted}
                        rowKey={(row) => row.id}
                        emptyMessage="No member is restricted."
                        renderActions={(row) => (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        index.url(),
                                        { customer_id: row.customer_id },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <ShieldCheck />
                                Manage
                            </Button>
                        )}
                    />
                </FormSection>

                <FormSection
                    icon={History}
                    color="purple"
                    title="History"
                    description="Every hold and block, newest first (last 100)."
                >
                    <DataTable
                        columns={historyColumns}
                        rows={history}
                        rowKey={(row) => row.id}
                        rowHref={(row) => showMember.url(row.member_id)}
                        emptyMessage="No restriction has been applied yet."
                    />
                </FormSection>
            </div>
        </>
    );
}
