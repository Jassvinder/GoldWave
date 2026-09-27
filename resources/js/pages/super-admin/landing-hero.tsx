import { Head, useForm, usePage } from '@inertiajs/react';
import { LayoutTemplate } from 'lucide-react';
import { FormEventHandler } from 'react';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/super-admin/landing-hero';

type Props = {
    headline: string;
    subtext: string;
    cta_primary_label: string;
    cta_secondary_label: string;
};

/** T-115 (19-09-2026) — Super-Admin-editable hero copy for the public landing page (`/`). */
export default function SuperAdminLandingHero({
    headline,
    subtext,
    cta_primary_label,
    cta_secondary_label,
}: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing } = useForm({
        headline,
        subtext,
        cta_primary_label,
        cta_secondary_label,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    return (
        <>
            <Head title="Landing Page Hero" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={LayoutTemplate}
                    color="amber"
                    title="Landing Page Hero"
                    description="Editable headline, subtext, and CTA button labels shown at the top of the public landing page (/)."
                    contentClassName="flex flex-col gap-4"
                >
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="headline">Headline</Label>
                            <Input
                                id="headline"
                                value={data.headline}
                                onChange={(e) =>
                                    setData('headline', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="subtext">Subtext</Label>
                            <textarea
                                id="subtext"
                                className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                                rows={4}
                                value={data.subtext}
                                onChange={(e) =>
                                    setData('subtext', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="cta_primary_label">
                                    Primary Button Label
                                </Label>
                                <Input
                                    id="cta_primary_label"
                                    value={data.cta_primary_label}
                                    onChange={(e) =>
                                        setData(
                                            'cta_primary_label',
                                            e.target.value,
                                        )
                                    }
                                />
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
                            </div>
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="self-start"
                        >
                            Save
                        </Button>
                    </form>
                </FormSection>
            </div>
        </>
    );
}
