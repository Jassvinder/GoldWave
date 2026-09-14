import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
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
import { store } from '@/routes/member/pending-profile';

type FormData = {
    pan_card: string;
    aadhaar_card: string;
    profile_photo: File | null;
    address: string;
    bank_account_holder_name: string;
    bank_account_number: string;
    bank_ifsc_code: string;
    bank_name: string;
    bank_proof_document: File | null;
};

/** INSTRUCTIONS.md M03 — the one-time Pending Fields form (DOMAIN_LOGIC.md §13); submittable exactly once. */
export default function PendingProfile() {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        pan_card: '',
        aadhaar_card: '',
        profile_photo: null,
        address: '',
        bank_account_holder_name: '',
        bank_account_number: '',
        bank_ifsc_code: '',
        bank_name: '',
        bank_proof_document: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <>
            <Head title="Complete Pending Profile" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Complete Pending Profile
                        </CardTitle>
                        <CardDescription>
                            This form can only be submitted once — double check
                            every field before continuing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="pan_card">PAN Card</Label>
                                <Input
                                    id="pan_card"
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
                                <Label htmlFor="aadhaar_card">
                                    Aadhaar Card
                                </Label>
                                <Input
                                    id="aadhaar_card"
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
                                <Label htmlFor="profile_photo">
                                    Profile Photo
                                </Label>
                                <Input
                                    id="profile_photo"
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

                            <div className="grid gap-2">
                                <Label htmlFor="address">Address</Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                />
                                {errors.address && (
                                    <p className="text-destructive text-sm">
                                        {errors.address}
                                    </p>
                                )}
                            </div>

                            <p className="mt-2 text-sm font-medium">
                                Bank Account Details
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_holder_name">
                                    Account Holder Name
                                </Label>
                                <Input
                                    id="bank_account_holder_name"
                                    value={data.bank_account_holder_name}
                                    onChange={(e) =>
                                        setData(
                                            'bank_account_holder_name',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.bank_account_holder_name && (
                                    <p className="text-destructive text-sm">
                                        {errors.bank_account_holder_name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_number">
                                    Account Number
                                </Label>
                                <Input
                                    id="bank_account_number"
                                    value={data.bank_account_number}
                                    onChange={(e) =>
                                        setData(
                                            'bank_account_number',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.bank_account_number && (
                                    <p className="text-destructive text-sm">
                                        {errors.bank_account_number}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_ifsc_code">
                                    IFSC Code
                                </Label>
                                <Input
                                    id="bank_ifsc_code"
                                    value={data.bank_ifsc_code}
                                    onChange={(e) =>
                                        setData(
                                            'bank_ifsc_code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                {errors.bank_ifsc_code && (
                                    <p className="text-destructive text-sm">
                                        {errors.bank_ifsc_code}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">Bank Name</Label>
                                <Input
                                    id="bank_name"
                                    value={data.bank_name}
                                    onChange={(e) =>
                                        setData('bank_name', e.target.value)
                                    }
                                />
                                {errors.bank_name && (
                                    <p className="text-destructive text-sm">
                                        {errors.bank_name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_proof_document">
                                    Passbook / Cancelled Cheque
                                </Label>
                                <Input
                                    id="bank_proof_document"
                                    type="file"
                                    accept="image/*,application/pdf"
                                    onChange={(e) =>
                                        setData(
                                            'bank_proof_document',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {errors.bank_proof_document && (
                                    <p className="text-destructive text-sm">
                                        {errors.bank_proof_document}
                                    </p>
                                )}
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-2"
                            >
                                Submit
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
