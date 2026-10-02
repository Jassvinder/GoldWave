import { NotificationsPage } from '@/components/notifications-page';
import type { NotificationsPageProps } from '@/types/notifications';

/** INSTRUCTIONS.md "Notifications" — the Admin / Store Owner's inbox. */
export default function AdminNotifications(props: NotificationsPageProps) {
    return <NotificationsPage {...props} />;
}
