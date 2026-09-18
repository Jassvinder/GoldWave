import { Head, useForm, usePage } from '@inertiajs/react';
import { Store as StoreIcon } from 'lucide-react';
import { FormEventHandler } from 'react';
import { FormSection } from '@/components/form-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { update } from '@/routes/admin/profile';

type Props = {
    store: {
        name: string;
        contact: string | null;
        location: string | null;
        status: string;
        jewellery_allocation_value: string;
        advance_amount: string;
        wallet_balance: string;
    };
};

/** INSTRUCTIONS.md A02 — assigned store details + the only editable fields (contact/location, DOMAIN_LOGIC.md §17.2). */
export default function AdminProfile({ store }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const { data, setData, post, processing } = useForm({
        contact: store.contact ?? '',
        location: store.location ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url());
    };

    return (
        <>
            <Head title="Store Profile" />

            <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="text-muted-foreground text-sm">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={StoreIcon}
                    color="blue"
                    title={store.name}
                    action={<Badge variant="secondary">{store.status}</Badge>}
                    contentClassName="flex flex-col gap-4"
                >
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground text-xs">
                                Jewellery Allocation
                            </div>
                            <div className="font-medium">
                                ₹{store.jewellery_allocation_value}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs">
                                Advance Amount
                            </div>
                            <div className="font-medium">
                                ₹{store.advance_amount}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs">
                                Store Wallet Balance
                            </div>
                            <div className="font-medium">
                                ₹{store.wallet_balance}
                            </div>
                        </div>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        Allocation, advance, and status are managed by Super
                        Admin — only contact and location can be updated here.
                    </p>

                    <Separator />

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="contact">Contact</Label>
                            <Input
                                id="contact"
                                value={data.contact}
                                onChange={(e) =>
                                    setData('contact', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="location">Location</Label>
                            <Input
                                id="location"
                                value={data.location}
                                onChange={(e) =>
                                    setData('location', e.target.value)
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="self-start"
                        >
                            Save Changes
                        </Button>
                    </form>
                </FormSection>
            </div>
        </>
    );
}
