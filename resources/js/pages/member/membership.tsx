import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEventHandler } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/utils';
import { bookCurrentRate } from '@/routes/member/membership';

type Plan = {
    code: string;
    name: string;
    amount: string;
    installment_count: number | null;
    product_category: string | null;
    fixed_weight_grams: string | null;
};

type RateBooking = {
    method: 'current_rate' | 'future_rate';
    installment_amount: string;
    total_installments: number;
    rate_per_gram: string | null;
    fixed_weight_grams: string | null;
    booked_at: string | null;
};

type Quote = {
    metal: string | null;
    fixed_weight_grams: number;
    metal_rate_id: number;
    rate_per_gram: number;
    total_value: number;
    paid_installments: number;
    paid_amount: number;
    remaining_value: number;
    pending_installments: number;
    maintenance_cost: number;
    installment_amount: number;
    total_remaining_payable: number;
    current_installment_amount: string;
};

type ProductBenefit = {
    metal: string | null;
    rate_per_gram_at_entry: string | null;
    entry_date: string | null;
    delivered_at: string | null;
    store_name: string | null;
};

type Props = {
    plan: Plan | null;
    rate_booking: RateBooking | null;
    current_rate_quote: Quote | null;
    product_benefits: ProductBenefit[];
};

const money = (value: number | string) =>
    `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** INSTRUCTIONS.md M05 — current plan, rate-booking state with "Book at Current Rate" (T-116), and product benefit entitlement (DOMAIN_LOGIC.md §3, §3.0). */
export default function Membership({
    plan,
    rate_booking,
    current_rate_quote,
    product_benefits,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;

    return (
        <>
            <Head title="Membership Plan" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Membership Plan
                        </CardTitle>
                        <CardDescription>
                            {plan
                                ? `${plan.name} — ₹${plan.amount}${plan.installment_count ? ` × ${plan.installment_count} months` : ' one-time'}`
                                : 'No active plan.'}
                        </CardDescription>
                    </CardHeader>
                    {plan && (
                        <CardContent className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Plan Code
                                </div>
                                <div className="font-medium">{plan.code}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">
                                    Product
                                </div>
                                <div className="font-medium capitalize">
                                    {plan.product_category ?? '—'}
                                </div>
                            </div>
                            {plan.fixed_weight_grams && (
                                <div>
                                    <div className="text-muted-foreground text-xs">
                                        Weight
                                    </div>
                                    <div className="font-medium">
                                        {plan.fixed_weight_grams}g
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    )}
                </Card>

                {rate_booking && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Rate</CardTitle>
                            <CardDescription>
                                {rate_booking.method === 'current_rate'
                                    ? rate_booking.booked_at
                                        ? `Booked at the Current Rate on ${formatDate(rate_booking.booked_at)}.`
                                        : 'Booked at the Current Rate.'
                                    : 'Future Rate — your jewellery weight is decided by the metal rate on the delivery date.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {rate_booking.method === 'current_rate' ? (
                                <div className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            Locked rate
                                        </div>
                                        <div className="font-medium">
                                            ₹{rate_booking.rate_per_gram}/g
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            Weight
                                        </div>
                                        <div className="font-medium">
                                            {rate_booking.fixed_weight_grams}g
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground text-xs">
                                            EMI
                                        </div>
                                        <div className="font-medium">
                                            ₹{rate_booking.installment_amount}
                                            /month
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-sm">
                                    <div className="text-muted-foreground text-xs">
                                        EMI
                                    </div>
                                    <div className="font-medium">
                                        ₹{rate_booking.installment_amount}/month
                                        × {rate_booking.total_installments}
                                    </div>
                                </div>
                            )}

                            {current_rate_quote && (
                                <BookCurrentRateDialog
                                    quote={current_rate_quote}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}

                {product_benefits.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Benefit</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable
                                columns={benefitColumns}
                                rows={product_benefits}
                                rowKey={(row, index) => index}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

/** The "Book at Current Rate" button and its popup: the figures come from the server's quote, and the confirm request echoes the rate id and paid-EMI count so a stale quote is refused. */
function BookCurrentRateDialog({ quote }: { quote: Quote }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        metal_rate_id: quote.metal_rate_id,
        paid_installments: quote.paid_installments,
    });
    const error = (form.errors as Record<string, string | undefined>).booking;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(bookCurrentRate.url(), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const rows: [string, string][] = [
        [
            'Metal and weight',
            `${quote.fixed_weight_grams}g ${quote.metal ?? ''}`.trim(),
        ],
        ['Today’s rate', `₹${quote.rate_per_gram}/g`],
        ['Total value at this rate', money(quote.total_value)],
        [
            `Paid so far (${quote.paid_installments} EMI${quote.paid_installments === 1 ? '' : 's'})`,
            money(quote.paid_amount),
        ],
        ['Remaining value', money(quote.remaining_value)],
        ['EMIs pending', String(quote.pending_installments)],
        ['Maintenance (1% of remaining value)', money(quote.maintenance_cost)],
    ];

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button className="w-fit">Book at Current Rate</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Book at Current Rate</DialogTitle>
                    <DialogDescription>
                        Your {quote.paid_installments} paid EMI
                        {quote.paid_installments === 1 ? '' : 's'} are credited
                        against the jewellery value, and the rest is spread over
                        your {quote.pending_installments} pending EMIs. This
                        cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <dl className="flex flex-col gap-2 text-sm">
                        {rows.map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                        <div className="flex justify-between gap-4 border-t pt-2">
                            <dt className="font-medium">New EMI</dt>
                            <dd className="font-semibold">
                                {money(quote.installment_amount)}
                                <span className="text-muted-foreground ml-1 text-xs font-normal">
                                    (now{' '}
                                    {money(quote.current_installment_amount)})
                                </span>
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">
                                Total still to pay
                            </dt>
                            <dd className="font-medium">
                                {money(quote.total_remaining_payable)}
                            </dd>
                        </div>
                    </dl>

                    <div
                        role="alert"
                        className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"
                    >
                        <p className="font-semibold">You can’t revert this.</p>
                        <p>
                            If you book by mistake you will have to contact the
                            Super Admin — and you must do it before you pay your
                            next EMI.
                        </p>
                    </div>

                    {error && (
                        <p className="text-destructive text-sm">{error}</p>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Confirm booking
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const benefitColumns: DataTableColumn<ProductBenefit>[] = [
    {
        key: 'metal',
        header: 'Metal',
        render: (row) => (
            <span className="font-medium capitalize">
                {row.metal ?? 'Metal TBD'}
            </span>
        ),
    },
    {
        key: 'rate_per_gram_at_entry',
        header: 'Rate at Entry',
        render: (row) =>
            row.rate_per_gram_at_entry
                ? `₹${row.rate_per_gram_at_entry}/g`
                : '—',
    },
    {
        key: 'entry_date',
        header: 'Booked',
        render: (row) => formatDate(row.entry_date),
    },
    {
        key: 'delivered_at',
        header: 'Delivery',
        render: (row) =>
            row.delivered_at
                ? `Delivered ${formatDate(row.delivered_at)} via ${row.store_name ?? 'a store'}`
                : 'Not yet delivered',
    },
];
