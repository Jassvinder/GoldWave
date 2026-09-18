import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { PortalHeader } from '@/components/portal-header';
import type { AppLayoutProps } from '@/types';

type PortalLayoutProps = AppLayoutProps & {
    sidebar: ReactNode;
};

/**
 * Shared shell for all three authenticated portals (T-100) — replaces the
 * three near-identical `super-admin-layout.tsx`/`admin-layout.tsx`/
 * `member-layout.tsx` wrappers, which differed only in which sidebar they
 * rendered. See `Docs/ARCHITECTURE.md`'s "Frontend Design System" section.
 */
export default function PortalLayout({
    sidebar,
    children,
    breadcrumbs = [],
}: PortalLayoutProps) {
    return (
        <AppShell variant="sidebar">
            {sidebar}
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <PortalHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
