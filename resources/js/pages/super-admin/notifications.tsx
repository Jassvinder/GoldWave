import { NotificationsPage } from '@/components/notifications-page';
import type { NotificationsPageProps } from '@/types/notifications';

/** INSTRUCTIONS.md "Notifications" — the Super Admin's inbox (cash payments to approve, profile change / payout requests, bank details to verify). */
export default function SuperAdminNotifications(props: NotificationsPageProps) {
    return <NotificationsPage {...props} />;
}
