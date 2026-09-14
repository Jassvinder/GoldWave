import { Head, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/utils';

type Calculation = {
    id: number;
    type: string;
    beneficiary_customer_id: string | null;
    rule_version_no: number;
    level_no: number | null;
    rate_percent: string;
    amount: string;
    eligibility_status: string;
    skip_reason: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    calculations: Paginated<Calculation>;
    filters: { type?: string; customer_id?: string; eligibility_status?: string; rule_version_id?: string };
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
    paid: 'default',
    skipped: 'secondary',
    pending: 'outline',
};

/** INSTRUCTIONS.md's Admin Compensation Management calculation audit page — source event, beneficiary, rule version, level/rate, amount, eligibility status/reason, timestamp. No reversal/reference field exists (DOMAIN_LOGIC.md §21's "Still open" — no rule describes reversing a finalized calculation). */
export default function SuperAdminCompensationAudit({
    calculations,
    filters,
}: Props) {
    const [customerId, setCustomerId] = useState(filters.customer_id ?? '');

    const submitFilter: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            '/super-admin/compensation/audit',
            { ...filters, customer_id: customerId },
            { preserveState: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            '/super-admin/compensation/audit',
            { ...filters, customer_id: customerId, page },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Compensation Calculation Audit" />

            <div className="mx-auto flex max-w-4xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Calculation Audit
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <form
                            onSubmit={submitFilter}
                            className="flex items-center gap-2"
                        >
                            <Input
                                placeholder="Filter by beneficiary Customer ID"
                                value={customerId}
                                onChange={(e) =>
                                    setCustomerId(e.target.value)
                                }
                            />
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>

                        <div className="flex flex-col gap-2">
                            {calculations.data.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No calculations found.
                                </p>
                            )}
                            {calculations.data.map((calc) => (
                                <div
                                    key={calc.id}
                                    className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                                >
                                    <div className="min-w-0">
                                        <div className="font-medium capitalize">
                                            {calc.type.replace(/_/g, ' ')}
                                            {calc.level_no
                                                ? ` — L${calc.level_no}`
                                                : ''}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {calc.beneficiary_customer_id ??
                                                '—'}{' '}
                                            · Rule v{calc.rule_version_no} ·{' '}
                                            {calc.rate_percent}% ·{' '}
                                            {formatDate(calc.created_at)}
                                            {calc.skip_reason
                                                ? ` · ${calc.skip_reason}`
                                                : ''}
                                        </div>
                                    </div>
                                    <div className="shrink-0 text-right whitespace-nowrap">
                                        <div className="font-medium">
                                            ₹{calc.amount}
                                        </div>
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[
                                                    calc.eligibility_status
                                                ] ?? 'outline'
                                            }
                                        >
                                            {calc.eligibility_status}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {calculations.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={calculations.current_page <= 1}
                                    onClick={() =>
                                        goToPage(
                                            calculations.current_page - 1,
                                        )
                                    }
                                >
                                    Previous
                                </Button>
                                <span className="text-muted-foreground text-sm">
                                    Page {calculations.current_page} of{' '}
                                    {calculations.last_page} (
                                    {calculations.total} calculations)
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        calculations.current_page >=
                                        calculations.last_page
                                    }
                                    onClick={() =>
                                        goToPage(
                                            calculations.current_page + 1,
                                        )
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
