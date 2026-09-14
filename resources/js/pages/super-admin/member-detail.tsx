import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/utils';

type MemberDetail = {
    id: number;
    customer_id: string;
    name: string | null;
    mobile: string | null;
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
    team_size: number;
    wallet_balance: string;
};

type Props = {
    member: MemberDetail;
    product_benefits: { metal: string | null; entry_date: string | null; delivered_at: string | null }[];
    emi: {
        total_installments: number;
        installments: { installment_no: number; due_date: string | null; amount: string; status: string }[];
    } | null;
    income: { type: string; level_no: number | null; amount: string; eligibility_status: string; created_at: string | null }[];
    wallet_ledger: { entry_type: string; category: string; amount: string; status: string; created_at: string | null }[];
    payouts: { requested_amount: string; status: string; created_at: string | null }[];
    draw_history: { group_no: number; is_winner_removed: boolean }[];
    booster_history: { level_no: number; qualified_at: string | null; paid_schedule_count: number }[];
    store_profit_distributions: { beneficiary_type: string; rate_percent: string; amount: string }[];
    activity: { type: string; description: string; occurred_at: string | null }[];
};

/** INSTRUCTIONS.md's Admin Member Management — full member detail: profile, plan, sponsor/placement, EMI, income, wallet, payouts, draw, booster, store-profit, activity. */
export default function SuperAdminMemberDetail({
    member,
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

            <div className="mx-auto flex max-w-4xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-2xl">
                            {member.customer_id} — {member.name ?? '—'}
                        </CardTitle>
                        <Badge variant="secondary">{member.status}</Badge>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <Field label="Mobile" value={member.mobile} />
                        <Field label="Email" value={member.email} />
                        <Field label="Plan" value={member.plan} />
                        <Field
                            label="Activated"
                            value={formatDate(member.activated_at)}
                        />
                        <Field
                            label="Sponsor"
                            value={member.sponsor_customer_id}
                        />
                        <Field
                            label="Placement Parent"
                            value={member.placement_parent_customer_id}
                        />
                        <Field
                            label="Placement Side"
                            value={member.placement_side}
                        />
                        <Field
                            label="Direct Count"
                            value={String(member.direct_count)}
                        />
                        <Field
                            label="Team Size"
                            value={String(member.team_size)}
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
