import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/payout-tds-settings';

type Props = {
    payout_min_amount: number;
    payout_tds_percent: number;
    payout_processing_fee_percent: number;
};

/** INSTRUCTIONS.md S08 — minimum withdrawal, TDS percentage, payout processing settings. */
export default function SuperAdminPayoutTdsSettings({
    payout_min_amount,
    payout_tds_percent,
    payout_processing_fee_percent,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing } = useForm({
        payout_min_amount,
        payout_tds_percent,
        payout_processing_fee_percent,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    return (
        <>
            <Head title="Payout & TDS Settings" />

            <div className="mx-auto flex max-w-md flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Payout & TDS Settings
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="payout_min_amount">
                                    Minimum Payout Amount
                                </Label>
                                <Input
                                    id="payout_min_amount"
                                    type="number"
                                    step="0.01"
                                    value={data.payout_min_amount}
                                    onChange={(e) =>
                                        setData(
                                            'payout_min_amount',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="payout_tds_percent">
                                    TDS Percentage
                                </Label>
                                <Input
                                    id="payout_tds_percent"
                                    type="number"
                                    step="0.01"
                                    value={data.payout_tds_percent}
                                    onChange={(e) =>
                                        setData(
                                            'payout_tds_percent',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="payout_processing_fee_percent">
                                    Processing Fee Percentage
                                </Label>
                                <Input
                                    id="payout_processing_fee_percent"
                                    type="number"
                                    step="0.01"
                                    value={data.payout_processing_fee_percent}
                                    onChange={(e) =>
                                        setData(
                                            'payout_processing_fee_percent',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="self-start"
                            >
                                Save Settings
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
