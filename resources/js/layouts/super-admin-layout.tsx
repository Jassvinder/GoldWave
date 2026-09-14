import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SuperAdminSidebar } from '@/components/super-admin-sidebar';
import type { AppLayoutProps } from '@/types';

/** INSTRUCTIONS.md's Super Admin Portal shell (T-017) — mirrors AdminLayout's structure with SuperAdminSidebar swapped in. */
export default function SuperAdminLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <SuperAdminSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
