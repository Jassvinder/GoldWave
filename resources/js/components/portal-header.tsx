import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavUserMenu } from '@/components/nav-user-menu';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Shared portal header (T-100) — replaces `AppSidebarHeader`'s bare
 * trigger+breadcrumbs with the reference design's bell/avatar row. The
 * header-wide search bar (T-113) was removed — non-functional, and every
 * page that needs search already has its own working one. The bell is the
 * real `NotificationBell` since T-140 (unread count, latest items, mark read).
 */
export function PortalHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center justify-between gap-4 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex shrink-0 items-center gap-1">
                <NotificationBell />
                <NavUserMenu />
            </div>
        </header>
    );
}
