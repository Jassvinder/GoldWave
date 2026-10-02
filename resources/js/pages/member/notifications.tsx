import { NotificationsPage } from '@/components/notifications-page';
import type { NotificationsPageProps } from '@/types/notifications';

/** INSTRUCTIONS.md M18 / "Notifications" — the member's inbox (cash payment decisions, request decisions, EMI reminders). */
export default function MemberNotifications(props: NotificationsPageProps) {
    return <NotificationsPage {...props} />;
}
