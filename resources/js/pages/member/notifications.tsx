import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { read as markRead } from '@/routes/member/notifications';
import { formatDate } from '@/lib/utils';

type Notification = {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
};

type Props = { notifications: Notification[] };

function describe(notification: Notification): string {
    if (notification.type === 'ProfileChangeRequestReviewed') {
        const status = notification.data.status as string;
        const field = notification.data.field_name as string;
        return `Your change request for ${field.replace(/_/g, ' ')} was ${status}.`;
    }

    return notification.type;
}

const columns: DataTableColumn<Notification>[] = [
    {
        key: 'type',
        header: 'Notification',
        render: (row) => describe(row),
    },
    {
        key: 'created_at',
        header: 'Date',
        render: (row) => formatDate(row.created_at),
    },
    {
        key: 'read_at',
        header: 'Status',
        render: (row) =>
            row.read_at ? (
                <Badge variant="secondary">Read</Badge>
            ) : (
                <Badge variant="default">Unread</Badge>
            ),
    },
];

/** INSTRUCTIONS.md M18 — system notifications inbox (database-channel notifications; see M04/M07 for request/status tracking). */
export default function Notifications({ notifications }: Props) {
    return (
        <>
            <Head title="Notifications" />

            <div className="flex w-full flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Notifications
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            rows={notifications}
                            rowKey={(row) => row.id}
                            emptyMessage="No notifications yet."
                            renderActions={(row) =>
                                !row.read_at ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(markRead.url(row.id))
                                        }
                                    >
                                        Mark read
                                    </Button>
                                ) : null
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
