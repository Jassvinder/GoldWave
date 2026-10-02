import { Head, useForm, usePage } from '@inertiajs/react';
import { LayoutTemplate, PhoneCall } from 'lucide-react';
import { FormEventHandler } from 'react';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/landing-hero';

type Contact = {
    phone: string;
    whatsapp: string;
    email: string;
    address: string;
};

type Props = {
    headline: string;
    subtext: string;
    cta_primary_label: string;
    cta_secondary_label: string;
    contact: Contact;
};

/** T-115 (19-09-2026) — Super-Admin-editable hero copy for the public landing page (`/`); contact details added 02-10-2026. */
export default function SuperAdminLandingHero({
    headline,
    subtext,
    cta_primary_label,
    cta_secondary_label,
    contact,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing, errors } = useForm({
        headline,
        subtext,
        cta_primary_label,
        cta_secondary_label,
        contact_phone: contact.phone,
        contact_whatsapp: contact.whatsapp,
        contact_email: contact.email,
        contact_address: contact.address,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    return (
        <>
            <Head title="Landing Page" />

            <form
                onSubmit={submit}
                className="mx-auto flex max-w-2xl flex-col gap-6 p-4"
            >
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={LayoutTemplate}
                    color="amber"
                    title="Hero"
                    description="Headline, subtext, and button labels shown at the top of the public landing page (/)."
                    contentClassName="flex flex-col gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="headline">Headline</Label>
                        <Input
                            id="headline"
                            value={data.headline}
                            onChange={(e) =>
                                setData('headline', e.target.value)
                            }
                        />
                        <InputError message={errors.headline} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="subtext">Subtext</Label>
                        <textarea
                            id="subtext"
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            rows={4}
                            value={data.subtext}
                            onChange={(e) => setData('subtext', e.target.value)}
                        />
                        <InputError message={errors.subtext} />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="cta_primary_label">
                                Primary Button Label
                            </Label>
                            <Input
                                id="cta_primary_label"
                                value={data.cta_primary_label}
                                onChange={(e) =>
                                    setData('cta_primary_label', e.target.value)
                                }
                            />
                            <InputError message={errors.cta_primary_label} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="cta_secondary_label">
                                Secondary Button Label
                            </Label>
                            <Input
                                id="cta_secondary_label"
                                value={data.cta_secondary_label}
                                onChange={(e) =>
                                    setData(
                                        'cta_secondary_label',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.cta_secondary_label} />
                        </div>
                    </div>
                </FormSection>

                <FormSection
                    icon={PhoneCall}
                    color="teal"
                    title="Contact Details"
                    description="Shown in the landing page's Contact section and footer. Leave a field blank to hide it."
                    contentClassName="flex flex-col gap-4"
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="contact_phone">Phone</Label>
                            <Input
                                id="contact_phone"
                                value={data.contact_phone}
                                onChange={(e) =>
                                    setData('contact_phone', e.target.value)
                                }
                            />
                            <InputError message={errors.contact_phone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact_whatsapp">WhatsApp</Label>
                            <Input
                                id="contact_whatsapp"
                                value={data.contact_whatsapp}
                                onChange={(e) =>
                                    setData('contact_whatsapp', e.target.value)
                                }
                            />
                            <InputError message={errors.contact_whatsapp} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contact_email">Email</Label>
                        <Input
                            id="contact_email"
                            type="email"
                            value={data.contact_email}
                            onChange={(e) =>
                                setData('contact_email', e.target.value)
                            }
                        />
                        <InputError message={errors.contact_email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="contact_address">Address</Label>
                        <textarea
                            id="contact_address"
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            rows={3}
                            value={data.contact_address}
                            onChange={(e) =>
                                setData('contact_address', e.target.value)
                            }
                        />
                        <InputError message={errors.contact_address} />
                    </div>
                </FormSection>

                <Button
                    type="submit"
                    disabled={processing}
                    className="self-start"
                >
                    Save
                </Button>
            </form>
        </>
    );
}
