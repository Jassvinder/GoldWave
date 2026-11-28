import { Head, usePage } from '@inertiajs/react';
import { IdCard } from 'lucide-react';
import { FormSection } from '@/components/form-section';
import {
    AddIdsDialog,
    PersonIdsTable,
    type AddIdsForm,
    type PersonId,
} from '@/components/person-ids';
import { store } from '@/routes/member/my-ids';

type Props = {
    ids: PersonId[];
    add_form: AddIdsForm;
};

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — My IDs: every ID this person holds (one login), and "Add ID" for more. New IDs are
 * sponsored by the first ID, can be placed under any member, and become active when their payment is approved.
 */
export default function MyIds({ ids, add_form }: Props) {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const firstId = ids.find((id) => id.is_first)?.customer_id ?? null;

    return (
        <>
            <Head title="My IDs" />

            <div className="flex w-full flex-col gap-6 p-4">
                {flash?.status && (
                    <p className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {flash.status}
                    </p>
                )}

                <FormSection
                    icon={IdCard}
                    color="amber"
                    title="My IDs"
                    description="All your member IDs. They share one login; use “Switch ID” at the top to work on another one. Each ID has its own wallet, EMIs and earnings."
                    contentClassName="flex flex-col gap-4"
                >
                    <PersonIdsTable ids={ids} />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        {add_form.can_add > 0 && (
                            <p className="text-muted-foreground text-sm">
                                {ids.length} of {add_form.limit} IDs used.
                            </p>
                        )}
                        <AddIdsDialog
                            form={add_form}
                            submitUrl={store.url()}
                            firstId={firstId}
                        />
                    </div>
                </FormSection>
            </div>
        </>
    );
}
