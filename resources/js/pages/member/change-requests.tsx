import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
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
import { Separator } from '@/components/ui/separator';
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

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Submit a Change Request
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
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
                                <Label htmlFor="reason">
                                    Reason (optional)
                                </Label>
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
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>My Requests</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {requests.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No change requests yet.
                            </p>
                        )}
                        {requests.map((request) => (
                            <div key={request.id}>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="font-medium">
                                            {FIELD_LABELS[request.field_name] ??
                                                request.field_name}
                                        </div>
                                        <div className="text-muted-foreground text-sm">
                                            {request.old_value ?? '—'} →{' '}
                                            {request.new_value ?? '—'}
                                        </div>
                                        {request.rejection_reason && (
                                            <div className="text-destructive text-sm">
                                                {request.rejection_reason}
                                            </div>
                                        )}
                                    </div>
                                    <Badge
                                        variant={STATUS_VARIANT[request.status]}
                                    >
                                        {request.status}
                                    </Badge>
                                </div>
                                <Separator className="mt-3" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
