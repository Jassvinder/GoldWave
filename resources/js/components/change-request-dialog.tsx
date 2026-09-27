import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import InputError from '@/components/input-error';
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
import { store } from '@/routes/member/change-requests';

export type ChangeRequestField =
    | 'pan_card'
    | 'aadhaar_card'
    | 'address'
    | 'profile_photo_path'
    | 'bank_details';

const LABELS: Record<ChangeRequestField, string> = {
    pan_card: 'PAN Card',
    aadhaar_card: 'Aadhaar Card',
    address: 'Address',
    profile_photo_path: 'Profile Photo',
    bank_details: 'Bank Details',
};

type FormData = {
    field_name: ChangeRequestField;
    return_to: string;
    reason: string;
    new_value: string;
    new_photo: File | null;
    bank_account_holder_name: string;
    bank_account_number: string;
    bank_ifsc_code: string;
    bank_name: string;
};

/**
 * "Request change" for one locked profile field, opened from the Profile page (T-143). Files the same Change Request as
 * the M04 page (`member.change-requests.store`) — the value stays as it is until the Super Admin approves — and returns
 * to the Profile page. The inputs match the field: text (PAN, Aadhaar, address), a photo upload, or the four bank fields.
 */
export function ChangeRequestDialog({ field }: { field: ChangeRequestField }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            field_name: field,
            return_to: 'profile',
            reason: '',
            new_value: '',
            new_photo: null,
            bank_account_holder_name: '',
            bank_account_number: '',
            bank_ifsc_code: '',
            bank_name: '',
        });
    const fieldErrors = errors as Record<string, string | undefined>;
    const label = LABELS[field];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground h-7 gap-1 px-2 text-xs"
                    aria-label={`Request change of ${label}`}
                >
                    <Pencil className="size-3" />
                    Request change
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Request change — {label}</DialogTitle>
                    <DialogDescription>
                        Your current {label.toLowerCase()} stays as it is until
                        the Super Admin approves this request. You will be
                        notified of the decision.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    {(field === 'pan_card' || field === 'aadhaar_card') && (
                        <div className="grid gap-2">
                            <Label htmlFor={`new_value_${field}`}>
                                New {label}
                            </Label>
                            <Input
                                id={`new_value_${field}`}
                                value={data.new_value}
                                onChange={(e) =>
                                    setData(
                                        'new_value',
                                        field === 'pan_card'
                                            ? e.target.value.toUpperCase()
                                            : e.target.value.replace(/\D/g, ''),
                                    )
                                }
                                maxLength={field === 'pan_card' ? 10 : 12}
                                placeholder={
                                    field === 'pan_card'
                                        ? 'ABCDE1234F'
                                        : '12 digits'
                                }
                                autoFocus
                            />
                            <InputError message={fieldErrors.new_value} />
                        </div>
                    )}

                    {field === 'address' && (
                        <div className="grid gap-2">
                            <Label htmlFor="new_value_address">
                                New address
                            </Label>
                            <textarea
                                id="new_value_address"
                                value={data.new_value}
                                onChange={(e) =>
                                    setData('new_value', e.target.value)
                                }
                                rows={3}
                                maxLength={1000}
                                autoFocus
                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            />
                            <InputError message={fieldErrors.new_value} />
                        </div>
                    )}

                    {field === 'profile_photo_path' && (
                        <div className="grid gap-2">
                            <Label htmlFor="new_photo">New photo</Label>
                            <Input
                                id="new_photo"
                                type="file"
                                accept="image/*"
                                onChange={(e) =>
                                    setData(
                                        'new_photo',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                JPG or PNG, up to 5 MB.
                            </p>
                            <InputError message={fieldErrors.new_photo} />
                        </div>
                    )}

                    {field === 'bank_details' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_holder">
                                    Account holder name
                                </Label>
                                <Input
                                    id="bank_holder"
                                    value={data.bank_account_holder_name}
                                    onChange={(e) =>
                                        setData(
                                            'bank_account_holder_name',
                                            e.target.value,
                                        )
                                    }
                                    autoFocus
                                />
                                <InputError
                                    message={
                                        fieldErrors.bank_account_holder_name
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_number">
                                    Account number
                                </Label>
                                <Input
                                    id="bank_number"
                                    inputMode="numeric"
                                    value={data.bank_account_number}
                                    onChange={(e) =>
                                        setData(
                                            'bank_account_number',
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                />
                                <InputError
                                    message={fieldErrors.bank_account_number}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="bank_ifsc">IFSC code</Label>
                                    <Input
                                        id="bank_ifsc"
                                        value={data.bank_ifsc_code}
                                        maxLength={11}
                                        onChange={(e) =>
                                            setData(
                                                'bank_ifsc_code',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={fieldErrors.bank_ifsc_code}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="bank_name">Bank name</Label>
                                    <Input
                                        id="bank_name"
                                        value={data.bank_name}
                                        onChange={(e) =>
                                            setData('bank_name', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={fieldErrors.bank_name}
                                    />
                                </div>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Changed bank details are verified again before
                                you can request a payout.
                            </p>
                        </>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor={`reason_${field}`}>
                            Reason{' '}
                            <span className="text-muted-foreground font-normal">
                                (optional)
                            </span>
                        </Label>
                        <Input
                            id={`reason_${field}`}
                            value={data.reason}
                            maxLength={500}
                            onChange={(e) => setData('reason', e.target.value)}
                            placeholder="Why does this need to change?"
                        />
                        <InputError message={fieldErrors.reason} />
                    </div>

                    <InputError message={fieldErrors.field_name} />
                    <InputError message={fieldErrors.pending_fields} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Send request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
