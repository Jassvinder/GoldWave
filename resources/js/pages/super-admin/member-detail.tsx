import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EditMemberDialog } from '@/components/edit-member-dialog';
import { RevertCurrentRateDialog } from '@/components/revert-current-rate-dialog';
import { ResetMemberPasswordDialog } from '@/components/reset-member-password-dialog';
import { formatDate, formatGender } from '@/lib/utils';
import { show as showDirects } from '@/routes/member/directs';
import {
    show as showMember,
    verifyBankDetail,
} from '@/routes/super-admin/members';
import { show as showTree } from '@/routes/member/tree';

type Leg = {
    total: number;
    active: number;
    inactive: number;
    dummy: number;
    store_owners: number;
};

type MemberDetail = {
    id: number;
    customer_id: string;
    name: string | null;
    mobile: string | null;
    gender: string | null;
    email: string | null;
    plan: string | null;
    sponsor_customer_id: string | null;
    status: string;
    activated_at: string | null;
    pan_card: string | null;
    aadhaar_card: string | null;
    address: string | null;
    pending_fields_submitted_at: string | null;
    placement_parent_customer_id: string | null;
    placement_side: string | null;
    direct_count: number;
    is_store_owner: boolean;
    network: {
        left: Leg;
        right: Leg;
        team_total: number;
        store_owners: number;
    };
    store_owners_in_downline: {
        member_id: number;
        customer_id: string | null;
        name: string | null;
        store_name: string;
        side: string;
    }[];
    wallet_balance: string;
};

type BankDetails = {
    account_holder_name: string | null;
    account_number: string | null;
    ifsc_code: string | null;
    bank_name: string | null;
    verified_at: string | null;
} | null;

type Props = {
    member: MemberDetail;
    bank_details: BankDetails;
    product_benefits: {
        metal: string | null;
        entry_date: string | null;
        delivered_at: string | null;
    }[];
    emi: {
        rate_booking: {
            method: 'current_rate' | 'future_rate';
            installment_amount: string;
            rate_per_gram: string | null;
            fixed_weight_grams: string | null;
            booked_at: string | null;
            can_revert: boolean;
            revert_blocker: string | null;
            events: {
                event: 'booked' | 'reverted';
                by: string | null;
                reason: string | null;
                occurred_at: string | null;
            }[];
        };
        total_installments: number;
        installments: {
            installment_no: number;
            due_date: string | null;
            amount: string;
            status: string;
        }[];
    } | null;
    income: {
        type: string;
        level_no: number | null;
        amount: string;
        eligibility_status: string;
        created_at: string | null;
    }[];
    wallet_ledger: {
        entry_type: string;
        category: string;
        amount: string;
        status: string;
        created_at: string | null;
    }[];
    payouts: {
        requested_amount: string;
        status: string;
        created_at: string | null;
    }[];
    draw_history: { group_no: number; is_winner_removed: boolean }[];
    booster_history: {
        level_no: number;
        qualified_at: string | null;
        paid_schedule_count: number;
    }[];
    store_profit_distributions: {
        beneficiary_type: string;
        rate_percent: string;
        amount: string;
    }[];
    activity: {
        type: string;
        description: string;
        occurred_at: string | null;
    }[];
};

/** INSTRUCTIONS.md's Admin Member Management — full member detail: profile, plan, sponsor/placement, EMI, income, wallet, payouts, draw, booster, store-profit, activity. */
export default function SuperAdminMemberDetail({
    member,
    bank_details,
    product_benefits,
    emi,
    income,
    wallet_ledger,
    payouts,
    draw_history,
    booster_history,
    store_profit_distributions,
    activity,
}: Props) {
    return (
        <>
            <Head title={member.customer_id} />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-2xl">
                            {member.customer_id} — {member.name ?? '—'}
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary">{member.status}</Badge>
                            <EditMemberDialog
                                member={member}
                                bankDetails={bank_details}
                            />
                            <ResetMemberPasswordDialog member={member} />
                        </div>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <Field label="Mobile" value={member.mobile} />
                        <Field
                            label="Gender"
                            value={formatGender(member.gender)}
                        />
                        <Field label="Email" value={member.email} />
                        <Field label="Plan" value={member.plan} />
                        <Field
                            label="Activated"
                            value={formatDate(member.activated_at)}
                        />
                        <Field
                            label="Wallet Balance"
                            value={`₹${member.wallet_balance}`}
                        />
                        <Field label="PAN Card" value={member.pan_card} />
                        <Field
                            label="Aadhaar Card"
                            value={member.aadhaar_card}
                        />
                        <Field label="Address" value={member.address} />
                        <Field
                            label="Bank Account"
                            value={
                                bank_details
                                    ? `${bank_details.bank_name ?? '—'} · ${bank_details.account_number ?? '—'}`
                                    : null
                            }
                        />
                        <div>
                            <div className="text-muted-foreground text-xs">
                                Bank Verified
                            </div>
                            {bank_details ? (
                                bank_details.verified_at ? (
                                    <div className="font-medium">
                                        {bank_details.verified_at}
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            Not verified
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    verifyBankDetail.url(
                                                        member.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Verify
                                        </Button>
                                    </div>
                                )
                            ) : (
                                <div className="font-medium">—</div>
                            )}
                        </div>
                        <Field
                            label="Pending Fields Submitted"
                            value={
                                member.pending_fields_submitted_at
                                    ? formatDate(
                                          member.pending_fields_submitted_at,
                                      )
                                    : 'Not submitted'
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Network</CardTitle>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={showDirects.url(member.id)}>
                                    View Directs
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={showTree.url(member.id)}>
                                    View Tree
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 text-sm">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Field
                                label="Sponsor"
                                value={member.sponsor_customer_id}
                            />
                            <Field
                                label="Position"
                                value={
                                    member.placement_side
                                        ? `${member.placement_side === 'left' ? 'Left' : 'Right'} of ${member.placement_parent_customer_id ?? '—'}`
                                        : 'Root'
                                }
                            />
                            <Field
                                label="Directs (Sponsor)"
                                value={String(member.direct_count)}
                            />
                            <Field
                                label="Total Team (Left + Right)"
                                value={String(member.network.team_total)}
                            />
                            <Field
                                label="Is Store Owner"
                                value={member.is_store_owner ? 'Yes' : 'No'}
                            />
                            <Field
                                label="Store Owners in Team"
                                value={String(member.network.store_owners)}
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <LegBox
                                label="Left Leg"
                                leg={member.network.left}
                            />
                            <LegBox
                                label="Right Leg"
                                leg={member.network.right}
                            />
                        </div>

                        {member.store_owners_in_downline.length > 0 && (
                            <div className="flex flex-col gap-2">
                                <p className="text-muted-foreground text-xs">
                                    Store Owners in this member&apos;s team
                                </p>
                                {member.store_owners_in_downline.map(
                                    (owner) => (
                                        <Link
                                            key={owner.member_id}
                                            href={showMember.url(
                                                owner.member_id,
                                            )}
                                            className="hover:border-primary flex flex-wrap items-center justify-between gap-2 rounded-md border p-2"
                                        >
                                            <span className="font-medium">
                                                {owner.customer_id} ·{' '}
                                                {owner.name ?? '—'}
                                            </span>
                                            <span className="text-muted-foreground capitalize">
                                                {owner.store_name} ·{' '}
                                                {owner.side} leg
                                            </span>
                                        </Link>
                                    ),
                                )}
                            </div>
                        )}

                        <p className="text-muted-foreground text-xs">
                            Team counts use the same numbers as Income Booster
                            and include unassigned dummy entries, shown
                            separately as &quot;dummy&quot;.
                        </p>
                    </CardContent>
                </Card>

                {product_benefits.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Benefit</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {product_benefits.map((benefit, index) => (
                                <div
                                    key={index}
                                    className="rounded-md border p-3 text-sm capitalize"
                                >
                                    {benefit.metal ?? 'Metal TBD'} · Booked{' '}
                                    {formatDate(benefit.entry_date)} ·{' '}
                                    {benefit.delivered_at
                                        ? `Delivered ${formatDate(benefit.delivered_at)}`
                                        : 'Not yet delivered'}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {emi && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                EMI Schedule ({emi.total_installments}{' '}
                                installments)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <div className="mb-2 flex flex-col gap-2 rounded-md border p-3 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span className="font-medium">
                                            {emi.rate_booking.method ===
                                            'current_rate'
                                                ? 'Current Rate'
                                                : 'Future Rate'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {emi.rate_booking.method ===
                                            'current_rate'
                                                ? ` — ${emi.rate_booking.fixed_weight_grams}g at ₹${emi.rate_booking.rate_per_gram}/g, EMI ₹${emi.rate_booking.installment_amount}${emi.rate_booking.booked_at ? `, booked ${formatDate(emi.rate_booking.booked_at)}` : ' (from registration)'}`
                                                : ` — EMI ₹${emi.rate_booking.installment_amount}`}
                                        </span>
                                    </div>
                                    {emi.rate_booking.can_revert && (
                                        <RevertCurrentRateDialog
                                            memberId={member.id}
                                        />
                                    )}
                                </div>
                                {emi.rate_booking.revert_blocker && (
                                    <p className="text-muted-foreground text-xs">
                                        Revert not available:{' '}
                                        {emi.rate_booking.revert_blocker}
                                    </p>
                                )}
                                {emi.rate_booking.events.map((event, index) => (
                                    <p
                                        key={index}
                                        className="text-muted-foreground text-xs"
                                    >
                                        {formatDate(event.occurred_at)} —{' '}
                                        {event.event === 'booked'
                                            ? 'Booked at Current Rate'
                                            : 'Reverted to Future Rate'}{' '}
                                        by {event.by ?? 'unknown'}
                                        {event.reason
                                            ? ` — "${event.reason}"`
                                            : ''}
                                    </p>
                                ))}
                            </div>
                            {emi.installments.map((installment) => (
                                <div
                                    key={installment.installment_no}
                                    className="flex items-start justify-between gap-3 rounded-md border p-2 text-sm"
                                >
                                    <div>
                                        Installment #
                                        {installment.installment_no} — Due{' '}
                                        {formatDate(installment.due_date)}
                                    </div>
                                    <div className="shrink-0 text-right whitespace-nowrap">
                                        ₹{installment.amount} ·{' '}
                                        {installment.status}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Income History</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {income.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No income yet.
                                </p>
                            )}
                            {income.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span className="capitalize">
                                        {row.type.replace(/_/g, ' ')}
                                        {row.level_no
                                            ? ` L${row.level_no}`
                                            : ''}
                                    </span>
                                    <span>
                                        ₹{row.amount} ·{' '}
                                        {formatDate(row.created_at)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Wallet Ledger</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {wallet_ledger.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No wallet activity yet.
                                </p>
                            )}
                            {wallet_ledger.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span className="capitalize">
                                        {row.category.replace(/_/g, ' ')}
                                    </span>
                                    <span>
                                        {row.entry_type === 'credit'
                                            ? '+'
                                            : '-'}
                                        ₹{row.amount} ·{' '}
                                        {formatDate(row.created_at)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payout History</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {payouts.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No payout requests yet.
                                </p>
                            )}
                            {payouts.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span>₹{row.requested_amount}</span>
                                    <span>
                                        {row.status} ·{' '}
                                        {formatDate(row.created_at)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Draw History</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {draw_history.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    Not part of any draw group.
                                </p>
                            )}
                            {draw_history.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span>Group #{row.group_no}</span>
                                    <span>
                                        {row.is_winner_removed
                                            ? 'Won'
                                            : 'Eligible'}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Booster History</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {booster_history.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No booster qualification yet.
                                </p>
                            )}
                            {booster_history.map((row, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span>Level {row.level_no}</span>
                                    <span>
                                        {row.paid_schedule_count} paid ·{' '}
                                        {formatDate(row.qualified_at)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {store_profit_distributions.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Store Profit History</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {store_profit_distributions.map(
                                    (row, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between text-sm capitalize"
                                        >
                                            <span>
                                                {row.beneficiary_type.replace(
                                                    /_/g,
                                                    ' ',
                                                )}
                                            </span>
                                            <span>
                                                {row.rate_percent}% — ₹
                                                {row.amount}
                                            </span>
                                        </div>
                                    ),
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Activity</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {activity.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No activity recorded yet.
                            </p>
                        )}
                        {activity.map((entry, index) => (
                            <div
                                key={index}
                                className="flex items-center justify-between rounded-md border p-2 text-sm"
                            >
                                <span>
                                    {entry.type}: {entry.description}
                                </span>
                                <span className="text-muted-foreground">
                                    {formatDate(entry.occurred_at)}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="font-medium">{value ?? '—'}</div>
        </div>
    );
}

function LegBox({ label, leg }: { label: string; leg: Leg }) {
    return (
        <div className="rounded-md border p-3">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="text-2xl font-semibold">{leg.total}</p>
            <p className="text-muted-foreground text-xs">
                {leg.active} active · {leg.inactive} inactive · {leg.dummy}{' '}
                dummy
            </p>
        </div>
    );
}
