import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import MemberLayout from '@/layouts/member-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SuperAdminLayout from '@/layouts/super-admin-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            // Public/pre-auth GoldWave pages (T-003) — these render before a member exists or
            // logs in, so they must not get the authenticated AppLayout sidebar shell.
            case name.startsWith('registration/'):
            case name.startsWith('payments/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            // GoldWave's Member Portal (T-015) — every M01-M18 page renders
            // inside the real member navigation shell, not the generic
            // starter-kit AppLayout.
            case name.startsWith('member/'):
                return MemberLayout;
            // GoldWave's Admin / Store Owner Portal (T-016) — every A01-A06
            // page renders inside the real store-operations navigation shell.
            case name.startsWith('admin/'):
                return AdminLayout;
            // GoldWave's Super Admin Portal (T-017) — every S01-S10 +
            // Admin Dashboard/Member/Compensation/Draw management page
            // renders inside the real company-control navigation shell.
            case name.startsWith('super-admin/'):
                return SuperAdminLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
