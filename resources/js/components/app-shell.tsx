import { router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { SidebarProvider, useSidebar } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider defaultOpen={isOpen}>
            <CloseMobileSidebarOnNavigate />
            {children}
        </SidebarProvider>
    );
}

/**
 * 01-10-2026 (user-reported) — on a phone the sidebar is a Sheet. Clicking a link used to change the page while the
 * Sheet was still open, leaving its overlay stuck on screen. Close it as soon as any Inertia visit starts, in every
 * portal.
 */
function CloseMobileSidebarOnNavigate() {
    const { openMobile, setOpenMobile } = useSidebar();

    useEffect(() => {
        if (!openMobile) {
            return;
        }

        return router.on('start', () => setOpenMobile(false));
    }, [openMobile, setOpenMobile]);

    return null;
}
