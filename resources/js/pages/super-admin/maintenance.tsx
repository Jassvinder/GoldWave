import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/super-admin/maintenance';

/**
 * T-174 — deliberately plain, low-key page (DOMAIN_LOGIC.md §2 "System Maintenance"). One text link; choosing a
 * side inserts an entry directly under the company root on that side.
 */
export default function SuperAdminMaintenance() {
    const flash = usePage().props.flash as { status?: string } | undefined;
    const [choosing, setChoosing] = useState(false);
    const [processing, setProcessing] = useState(false);

    const run = (side: 'left' | 'right') => {
        router.post(
            store.url(),
            { side },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setChoosing(false);
                },
            },
        );
    };

    return (
        <>
            <Head title="Maintenance" />

            <div className="flex max-w-2xl flex-col gap-4 p-4 text-sm">
                <h1 className="text-lg font-semibold">System Maintenance</h1>
                <p className="text-muted-foreground">
                    Routine housekeeping tasks for the system.
                </p>

                {flash?.status && (
                    <p className="text-muted-foreground">{flash.status}</p>
                )}

                {!choosing ? (
                    <div>
                        <button
                            type="button"
                            className="text-primary underline-offset-4 hover:underline"
                            onClick={() => setChoosing(true)}
                        >
                            Run structure maintenance
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground">Side:</span>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={processing}
                            onClick={() => run('left')}
                        >
                            Left
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={processing}
                            onClick={() => run('right')}
                        >
                            Right
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={processing}
                            onClick={() => setChoosing(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
