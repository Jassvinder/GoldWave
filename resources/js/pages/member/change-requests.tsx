import { Head, useForm } from '@inertiajs/react';
import { FilePenLine } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/member/change-requests';

type ChangeRequest = {
    id: number;
    field_name: string;
    old_value: string | null;
    new_value: string | null;
    reason: string | null;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    reviewed_at: string | null;
};

type Props = {
    requests: ChangeRequest[];
    pending_fields_submitted: boolean;
};

const FIELD_LABELS: Record<string, string> = {
    pan_card: 'PAN Card',
    aadhaar_card: 'Aadhaar Card',
    address: 'Address',
    profile_photo_path: 'Profile Photo',
    bank_details: 'Bank Details',
};

const STATUS_VARIANT: Record<
    ChangeRequest['status'],
    'default' | 'secondary' | 'destructive'
> = {
    pending: 'secondary',
    approved: 'default',
    rejected: 'destructive',
};

type FormData = {
    field_name: string;
    reason: string;
    new_value: string;
    new_photo: File | null;
    bank_account_holder_name: string;
    bank_account_number: string;
    bank_ifsc_code: string;
    bank_name: string;
};

/** INSTRUCTIONS.md M04 — submit + track correction requests for locked fields (DOMAIN_LOGIC.md §13 point 3). */
export default function ChangeRequests({
    requests,
    pending_fields_submitted,
}: Props) {
    const [fieldName, setFieldName] = useState('pan_card');
    const { data, setData, post, processing, errors, reset } =
        useForm<FormData>({
            field_name: 'pan_card',
            reason: '',
            new_value: '',
            new_photo: null,
            bank_account_holder_name: '',
            bank_account_number: '',
            bank_ifsc_code: '',
            bank_name: '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), { onSuccess: () => reset() });
    };

    if (!pending_fields_submitted) {
        return (
            <>
                <Head title="Change Requests" />
                <div className="mx-auto max-w-2xl p-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Change Requests</CardTitle>
                            <CardDescription>
                                Complete your Pending Profile fields first —
                                change requests only apply to already-locked
                                fields.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Change Requests" />

            <div className="flex w-full flex-col gap-6 p-4">
                <FormSection
                    icon={FilePenLine}
                    color="blue"
                    title="Submit a Change Request"
                >
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label>Field</Label>
                            <Select
                                value={fieldName}
                                onValueChange={(value) => {
                                    setFieldName(value);
                                    setData('field_name', value);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(FIELD_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        {fieldName === 'bank_details' ? (
                            <>
                                <Input
                                    placeholder="Account Holder Name"
                                    value={data.bank_account_holder_name}
                                    onChange={(e) =>
                                        setData(
                                            'bank_account_holder_name',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Input
                                    placeholder="Account Number"
                                    value={data.bank_account_number}
                                    onChange={(e) =>
                                        setData(
                                            'bank_account_number',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Input
                                    placeholder="IFSC Code"
                                    value={data.bank_ifsc_code}
                                    onChange={(e) =>
                                        setData(
                                            'bank_ifsc_code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                <Input
                                    placeholder="Bank Name"
                                    value={data.bank_name}
                                    onChange={(e) =>
                                        setData('bank_name', e.target.value)
                                    }
                                />
                            </>
                        ) : fieldName === 'profile_photo_path' ? (
                            <Input
                                type="file"
                                accept="image/*"
                                onChange={(e) =>
                                    setData(
                                        'new_photo',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        ) : (
                            <Input
                                placeholder="New value"
                                value={data.new_value}
                                onChange={(e) =>
                                    setData('new_value', e.target.value)
                                }
                            />
                        )}
                        {errors.new_value && (
                            <p className="text-destructive text-sm">
                                {errors.new_value}
                            </p>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="reason">Reason (optional)</Label>
                            <Input
                                id="reason"
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                            />
                        </div>

                        <Button type="submit" disabled={processing}>
                            Submit Request
                        </Button>
                    </form>
                </FormSection>

                <Card>
                    <CardHeader>
                        <CardTitle>My Requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={requestColumns}
                            rows={requests}
                            rowKey={(row) => row.id}
                            emptyMessage="No change requests yet."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

const requestColumns: DataTableColumn<ChangeRequest>[] = [
    {
        key: 'field_name',
        header: 'Field',
        render: (row) => (
            <span className="font-medium">
                {FIELD_LABELS[row.field_name] ?? row.field_name}
            </span>
        ),
    },
    {
        key: 'new_value',
        header: 'Change',
        render: (row) => (
            <span>
                {row.old_value ?? '—'} → {row.new_value ?? '—'}
            </span>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <div className="flex flex-col gap-0.5">
                <Badge variant={STATUS_VARIANT[row.status]}>{row.status}</Badge>
                {row.rejection_reason && (
                    <span className="text-destructive text-xs">
                        {row.rejection_reason}
                    </span>
                )}
            </div>
        ),
    },
];
