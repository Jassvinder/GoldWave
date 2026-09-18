import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { formatDate } from '@/lib/utils';

type Plan = {
    code: string;
    name: string;
    amount: string;
    installment_count: number | null;
    product_category: string | null;
    fixed_weight_grams: string | null;
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
    product_benefits: ProductBenefit[];
};

/** INSTRUCTIONS.md M05 — current plan + product benefit entitlement (DOMAIN_LOGIC.md §3). */
export default function Membership({ plan, product_benefits }: Props) {
    return (
        <>
            <Head title="Membership Plan" />

            <div className="flex w-full flex-col gap-6 p-4">
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
