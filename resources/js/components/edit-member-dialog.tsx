import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/members';

type BankDetails = {
    account_holder_name: string | null;
    account_number: string | null;
    ifsc_code: string | null;
    bank_name: string | null;
    verified_at: string | null;
} | null;

type Props = {
    member: {
        id: number;
        name: string | null;
        email: string | null;
        mobile: string | null;
        pan_card: string | null;
        aadhaar_card: string | null;
        address: string | null;
    };
    bankDetails: BankDetails;
};

/**
 * T-106 (17-09-2026) — Super Admin's direct Member edit dialog. Per the
 * user's explicit instruction, every field except Customer ID is editable
 * here, bypassing the member's own Change Request/approval workflow (that
 * stays a separate channel for member self-service edits).
 */
export function EditMemberDialog({ member, bankDetails }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: member.name ?? '',
        email: member.email ?? '',
        mobile: member.mobile ?? '',
        pan_card: member.pan_card ?? '',
        aadhaar_card: member.aadhaar_card ?? '',
        address: member.address ?? '',
        profile_photo: null as File | null,
        bank_account_holder_name: bankDetails?.account_holder_name ?? '',
        bank_account_number: bankDetails?.account_number ?? '',
        bank_ifsc_code: bankDetails?.ifsc_code ?? '',
        bank_name: bankDetails?.bank_name ?? '',
        bank_proof_document: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(update.url(member.id), {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) reset();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit Member Details</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit_name">Name</Label>
                        <Input
                            id="edit_name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && (
                            <p className="text-destructive text-sm">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_email">Email</Label>
                        <Input
                            id="edit_email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && (
                            <p className="text-destructive text-sm">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_mobile">Mobile</Label>
                        <Input
                            id="edit_mobile"
                            value={data.mobile}
                            onChange={(e) => setData('mobile', e.target.value)}
                        />
                        {errors.mobile && (
                            <p className="text-destructive text-sm">
                                {errors.mobile}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_pan_card">PAN Card</Label>
                        <Input
                            id="edit_pan_card"
                            value={data.pan_card}
                            onChange={(e) =>
                                setData(
                                    'pan_card',
                                    e.target.value.toUpperCase(),
                                )
                            }
                            placeholder="ABCDE1234F"
                        />
                        {errors.pan_card && (
                            <p className="text-destructive text-sm">
                                {errors.pan_card}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_aadhaar_card">Aadhaar Card</Label>
                        <Input
                            id="edit_aadhaar_card"
                            value={data.aadhaar_card}
                            onChange={(e) =>
                                setData('aadhaar_card', e.target.value)
                            }
                            placeholder="123456789012"
                        />
                        {errors.aadhaar_card && (
                            <p className="text-destructive text-sm">
                                {errors.aadhaar_card}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_address">Address</Label>
                        <Input
                            id="edit_address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                        {errors.address && (
                            <p className="text-destructive text-sm">
                                {errors.address}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit_profile_photo">
                            Profile Photo (leave empty to keep existing)
                        </Label>
                        <Input
                            id="edit_profile_photo"
                            type="file"
                            accept="image/*"
                            onChange={(e) =>
                                setData(
                                    'profile_photo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        {errors.profile_photo && (
                            <p className="text-destructive text-sm">
                                {errors.profile_photo}
                            </p>
                        )}
                    </div>

                    <div className="border-t pt-4">
                        <p className="mb-3 text-sm font-medium">
                            Bank Account Details
                            {bankDetails?.verified_at && (
                                <span className="text-muted-foreground ml-2 text-xs font-normal">
                                    Verified {bankDetails.verified_at} —
                                    changing these resets verification.
                                </span>
                            )}
                        </p>

                        <div className="grid gap-2">
                            <Label htmlFor="edit_bank_account_holder_name">
                                Account Holder Name
                            </Label>
                            <Input
                                id="edit_bank_account_holder_name"
                                value={data.bank_account_holder_name}
                                onChange={(e) =>
                                    setData(
                                        'bank_account_holder_name',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="mt-2 grid gap-2">
                            <Label htmlFor="edit_bank_account_number">
                                Account Number
                            </Label>
                            <Input
                                id="edit_bank_account_number"
                                value={data.bank_account_number}
                                onChange={(e) =>
                                    setData(
                                        'bank_account_number',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="mt-2 grid gap-2">
                            <Label htmlFor="edit_bank_ifsc_code">
                                IFSC Code
                            </Label>
                            <Input
                                id="edit_bank_ifsc_code"
                                value={data.bank_ifsc_code}
                                onChange={(e) =>
                                    setData(
                                        'bank_ifsc_code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </div>

                        <div className="mt-2 grid gap-2">
                            <Label htmlFor="edit_bank_name">Bank Name</Label>
                            <Input
                                id="edit_bank_name"
                                value={data.bank_name}
                                onChange={(e) =>
                                    setData('bank_name', e.target.value)
                                }
                            />
                        </div>

                        <div className="mt-2 grid gap-2">
                            <Label htmlFor="edit_bank_proof_document">
                                Passbook / Cancelled Cheque (leave empty to keep
                                existing)
                            </Label>
                            <Input
                                id="edit_bank_proof_document"
                                type="file"
                                accept="image/*,application/pdf"
                                onChange={(e) =>
                                    setData(
                                        'bank_proof_document',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
