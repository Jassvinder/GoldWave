import { MemberSidebar } from '@/components/member-sidebar';
import PortalLayout from '@/layouts/portal-layout';
import type { AppLayoutProps } from '@/types';

/**
 * INSTRUCTIONS.md's Member Portal shell (T-015) — every `member/*` page
 * (M01-M18) renders inside this sidebar shell instead of the generic
 * Fortify-scaffolded `AppLayout` (which only ever listed "Dashboard" in its
 * nav — a leftover from the starter kit, never the real Member Portal
 * navigation). Delegates its structure to the shared `PortalLayout` (T-100),
 * swapping in `MemberSidebar` for the generic `AppSidebar`.
 */
export default function MemberLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <PortalLayout sidebar={<MemberSidebar />} breadcrumbs={breadcrumbs}>
            {children}
        </PortalLayout>
    );
}
