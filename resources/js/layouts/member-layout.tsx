import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { MemberSidebar } from '@/components/member-sidebar';
import type { AppLayoutProps } from '@/types';

/**
 * INSTRUCTIONS.md's Member Portal shell (T-015) — every `member/*` page
 * (M01-M18) renders inside this sidebar shell instead of the generic
 * Fortify-scaffolded `AppLayout` (which only ever listed "Dashboard" in its
 * nav — a leftover from the starter kit, never the real Member Portal
 * navigation). Mirrors `AppSidebarLayout`'s structure exactly, swapping in
 * `MemberSidebar` for the generic `AppSidebar`.
 */
export default function MemberLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <MemberSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
