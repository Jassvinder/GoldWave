import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, Landmark, User } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    ChangeRequestDialog,
    type ChangeRequestField,
} from '@/components/change-request-dialog';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatGender } from '@/lib/utils';
import { index as changeRequestsIndex } from '@/routes/member/change-requests';
import { create as createPendingProfile } from '@/routes/member/pending-profile';

type Props = {
    member: {
        customer_id: string;
        name: string | null;
        email: string | null;
        mobile: string | null;
        gender: string | null;
        status: string;
        activated_at: string | null;
        pan_card: string | null;
        aadhaar_card: string | null;
        profile_photo_url: string | null;
        address: string | null;
        pending_fields_submitted_at: string | null;
        sponsor_customer_id: string | null;
        sponsor_name: string | null;
    };
    bank_detail: {
        account_holder_name: string;
        account_number: string;
        ifsc_code: string;
        bank_name: string;
        verified_at: string | null;
    } | null;
    pending_change_fields: string[];
};

function Field({
    label,
    value,
    action,
}: {
    label: string;
    value: string | null;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="font-medium break-words">{value ?? '—'}</div>
            {action && <div className="mt-0.5 -ml-2">{action}</div>}
        </div>
    );
}

/** INSTRUCTIONS.md M02 — the member's profile. Once the one-time Pending Fields are submitted they are locked; a change is asked for with "Request change" right next to the field (T-143). */
export default function Profile({
    member,
    bank_detail,
    pending_change_fields,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const locked = member.pending_fields_submitted_at !== null;

    /** The "Request change" button for a locked field, or a "Change requested" badge while one is already waiting. */
    const changeAction = (field: ChangeRequestField): ReactNode => {
        if (!locked) {
            return null;
        }

        return pending_change_fields.includes(field) ? (
            <Badge variant="secondary" className="ml-2 gap-1">
                <Clock className="size-3" />
                Change requested
            </Badge>
        ) : (
            <ChangeRequestDialog field={field} />
        );
    };

    const placeholder =
        member.gender === 'female'
            ? '/Images/avtar_female.webp'
            : '/Images/avtar_male.webp';

    return (
        <>
            <Head title="My Profile" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/25 bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-400"
                    >
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={User}
                    color="blue"
                    title="My Profile"
                    action={
                        <Badge
                            variant={
                                member.status === 'active'
                                    ? 'success'
                                    : 'secondary'
                            }
                            className="capitalize"
                        >
                            {member.status}
                        </Badge>
                    }
                    contentClassName="flex flex-col gap-5"
                >
                    <div className="flex items-center gap-4">
                        <img
                            src={member.profile_photo_url ?? placeholder}
                            alt=""
                            className="size-20 shrink-0 rounded-full border object-cover"
                        />
                        <div className="min-w-0">
                            <div className="text-lg font-semibold break-words">
                                {member.name ?? 'Unnamed member'}
                            </div>
                            <div className="text-muted-foreground text-sm">
                                {member.customer_id}
                            </div>
                            {changeAction('profile_photo_path') && (
                                <div className="mt-1 -ml-2">
                                    {changeAction('profile_photo_path')}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <Field label="Email" value={member.email} />
                        <Field label="Mobile" value={member.mobile} />
                        <Field
                            label="Gender"
                            value={formatGender(member.gender)}
                        />
                        <Field
                            label="Activated"
                            value={formatDate(member.activated_at)}
                        />
                        <Field
                            label="Sponsor"
                            value={
                                member.sponsor_customer_id
                                    ? `${member.sponsor_customer_id}${member.sponsor_name ? ` — ${member.sponsor_name}` : ''}`
                                    : null
                            }
                        />
                    </div>
                </FormSection>

                <FormSection
                    icon={Landmark}
                    color="purple"
                    title="Identity & Address"
                    description={
                        locked
                            ? 'These details are locked. Use "Request change" to ask the Super Admin to update one.'
                            : undefined
                    }
                    contentClassName="grid grid-cols-1 gap-4 sm:grid-cols-3"
                >
                    <Field
                        label="PAN Card"
                        value={member.pan_card}
                        action={changeAction('pan_card')}
                    />
                    <Field
                        label="Aadhaar Card"
                        value={member.aadhaar_card}
                        action={changeAction('aadhaar_card')}
                    />
                    <Field
                        label="Address"
                        value={member.address}
                        action={changeAction('address')}
                    />
                </FormSection>

                {bank_detail && (
                    <FormSection
                        icon={Landmark}
                        color="green"
                        title="Bank Details"
                        action={
                            <Badge
                                variant={
                                    bank_detail.verified_at
                                        ? 'success'
                                        : 'secondary'
                                }
                            >
                                {bank_detail.verified_at
                                    ? `Verified ${formatDate(bank_detail.verified_at)}`
                                    : 'Pending verification'}
                            </Badge>
                        }
                        contentClassName="grid grid-cols-2 gap-4 sm:grid-cols-3"
                    >
                        <Field
                            label="Account holder"
                            value={bank_detail.account_holder_name}
                        />
                        <Field label="Bank" value={bank_detail.bank_name} />
                        <Field
                            label="Account Number"
                            value={bank_detail.account_number}
                        />
                        <Field label="IFSC" value={bank_detail.ifsc_code} />
                        <div className="col-span-2 sm:col-span-3">
                            {changeAction('bank_details')}
                        </div>
                    </FormSection>
                )}

                {!locked ? (
                    <div className="flex flex-col gap-2">
                        <p className="text-muted-foreground text-sm">
                            Complete your remaining profile details once — after
                            that they are locked and can only be changed through
                            a request.
                        </p>
                        <Button asChild className="self-start">
                            <Link href={createPendingProfile()}>
                                Complete Pending Profile
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <Button variant="outline" asChild className="self-start">
                        <Link href={changeRequestsIndex()}>
                            View my change requests
                        </Link>
                    </Button>
                )}
            </div>
        </>
    );
}
