import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDate } from '@/lib/utils';
import { index as changeRequestsIndex } from '@/routes/member/change-requests';
import { create as createPendingProfile } from '@/routes/member/pending-profile';

type Props = {
    member: {
        customer_id: string;
        name: string | null;
        email: string | null;
        mobile: string | null;
        status: string;
        activated_at: string | null;
        pan_card: string | null;
        aadhaar_card: string | null;
        profile_photo_path: string | null;
        address: string | null;
        pending_fields_submitted_at: string | null;
    };
    bank_detail: {
        account_holder_name: string;
        account_number: string;
        ifsc_code: string;
        bank_name: string;
        verified_at: string | null;
    } | null;
};

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="font-medium">{value ?? '—'}</div>
        </div>
    );
}

/** INSTRUCTIONS.md M02 — read-only profile view; lock state gates whether Complete Profile (M03) or Change Request (M04) applies. */
export default function Profile({ member, bank_detail }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const locked = member.pending_fields_submitted_at !== null;

    return (
        <>
            <Head title="My Profile" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-2xl">My Profile</CardTitle>
                        <Badge variant="secondary">{member.status}</Badge>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Field
                                label="Customer ID"
                                value={member.customer_id}
                            />
                            <Field label="Name" value={member.name} />
                            <Field label="Email" value={member.email} />
                            <Field label="Mobile" value={member.mobile} />
                            <Field
                                label="Activated"
                                value={formatDate(member.activated_at)}
                            />
                        </div>

                        <Separator />

                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Field label="PAN Card" value={member.pan_card} />
                            <Field
                                label="Aadhaar Card"
                                value={member.aadhaar_card}
                            />
                            <Field label="Address" value={member.address} />
                        </div>

                        {bank_detail && (
                            <>
                                <Separator />
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                    <Field
                                        label="Bank"
                                        value={bank_detail.bank_name}
                                    />
                                    <Field
                                        label="Account Number"
                                        value={bank_detail.account_number}
                                    />
                                    <Field
                                        label="IFSC"
                                        value={bank_detail.ifsc_code}
                                    />
                                    <Field
                                        label="Verification"
                                        value={
                                            bank_detail.verified_at
                                                ? `Verified ${formatDate(bank_detail.verified_at)}`
                                                : 'Pending verification'
                                        }
                                    />
                                </div>
                            </>
                        )}

                        <Separator />

                        {!locked ? (
                            <Button asChild>
                                <Link href={createPendingProfile()}>
                                    Complete Pending Profile
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" asChild>
                                <Link href={changeRequestsIndex()}>
                                    Request a Change
                                </Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
