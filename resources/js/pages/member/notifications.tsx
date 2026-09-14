import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

/** INSTRUCTIONS.md M18 — system notifications inbox (database-channel notifications; see M04/M07 for request/status tracking). */
export default function Notifications({ notifications }: Props) {
    return (
        <>
            <Head title="Notifications" />

            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Notifications
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {notifications.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No notifications yet.
                            </p>
                        )}
                        {notifications.map((notification) => (
                            <div
                                key={notification.id}
                                className="flex items-center justify-between rounded-md border p-3"
                            >
                                <div>
                                    <div className="font-medium">
                                        {describe(notification)}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {formatDate(notification.created_at)}
                                    </div>
                                </div>
                                {!notification.read_at ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                markRead.url(notification.id),
                                            )
                                        }
                                    >
                                        Mark read
                                    </Button>
                                ) : (
                                    <Badge variant="secondary">Read</Badge>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
