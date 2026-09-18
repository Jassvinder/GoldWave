import { AdminSidebar } from '@/components/admin-sidebar';
import PortalLayout from '@/layouts/portal-layout';
import type { AppLayoutProps } from '@/types';

/** INSTRUCTIONS.md's Admin / Store Owner Portal shell (T-016) — delegates to the shared `PortalLayout` (T-100) with `AdminSidebar` swapped in. */
export default function AdminLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <PortalLayout sidebar={<AdminSidebar />} breadcrumbs={breadcrumbs}>
            {children}
        </PortalLayout>
    );
}
