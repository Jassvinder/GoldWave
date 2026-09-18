import { SuperAdminSidebar } from '@/components/super-admin-sidebar';
import PortalLayout from '@/layouts/portal-layout';
import type { AppLayoutProps } from '@/types';

/** INSTRUCTIONS.md's Super Admin Portal shell (T-017) — delegates to the shared `PortalLayout` (T-100) with `SuperAdminSidebar` swapped in. */
export default function SuperAdminLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <PortalLayout sidebar={<SuperAdminSidebar />} breadcrumbs={breadcrumbs}>
            {children}
        </PortalLayout>
    );
}
