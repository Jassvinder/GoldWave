import { Link } from '@inertiajs/react';
import {
    Banknote,
    ClipboardList,
    Coins,
    FileSpreadsheet,
    LayoutGrid,
    Percent,
    Settings2,
    Store as StoreIcon,
    Trophy,
    UserCheck,
    UserCog,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { index as adminUsersIndex } from '@/routes/super-admin/admin-users';
import { audit as compensationAudit } from '@/routes/super-admin/compensation';
import { index as drawManagementIndex } from '@/routes/super-admin/draw-management';
import { index as drawSettingsIndex } from '@/routes/super-admin/draw-settings';
import { index as dummyAssignmentIndex } from '@/routes/super-admin/dummy-entry-assignment';
import { index as dummySettingsIndex } from '@/routes/super-admin/dummy-entry-settings';
import { index as membersIndex } from '@/routes/super-admin/members';
import { index as metalRatesIndex } from '@/routes/super-admin/metal-rates';
import { index as payoutTdsIndex } from '@/routes/super-admin/payout-tds-settings';
import { index as reportsIndex } from '@/routes/super-admin/reports';
import { index as ruleVersionsIndex } from '@/routes/super-admin/rule-versions';
import { index as storeManagementIndex } from '@/routes/super-admin/store-management';
import { index as storeWalletsIndex } from '@/routes/super-admin/store-wallets';
import type { NavItem } from '@/types';

const overviewItems: NavItem[] = [
    { title: 'System Dashboard', href: dashboard(), icon: LayoutGrid },
];

const memberItems: NavItem[] = [
    { title: 'Admin Users', href: adminUsersIndex(), icon: UserCog },
    { title: 'Member Management', href: membersIndex(), icon: Users },
    { title: 'Dummy Entry Settings', href: dummySettingsIndex(), icon: UserPlus },
    { title: 'Dummy Entry Assignment', href: dummyAssignmentIndex(), icon: UserCheck },
];

const compensationItems: NavItem[] = [
    { title: 'Rule Versions', href: ruleVersionsIndex(), icon: Percent },
    { title: 'Compensation Audit', href: compensationAudit(), icon: ClipboardList },
];

const drawItems: NavItem[] = [
    { title: 'Draw Settings', href: drawSettingsIndex(), icon: Settings2 },
    { title: 'Draw Management', href: drawManagementIndex(), icon: Trophy },
];

const settingsItems: NavItem[] = [
    { title: 'Gold & Silver Rates', href: metalRatesIndex(), icon: Coins },
    { title: 'Payout & TDS Settings', href: payoutTdsIndex(), icon: Banknote },
];

const storeItems: NavItem[] = [
    { title: 'Store Management', href: storeManagementIndex(), icon: StoreIcon },
    { title: 'Store Wallets', href: storeWalletsIndex(), icon: Wallet },
];

const reportItems: NavItem[] = [
    { title: 'Reports', href: reportsIndex(), icon: FileSpreadsheet },
];

const sections: { label: string; items: NavItem[] }[] = [
    { label: 'Overview', items: overviewItems },
    { label: 'Members', items: memberItems },
    { label: 'Compensation', items: compensationItems },
    { label: 'Draw', items: drawItems },
    { label: 'Settings', items: settingsItems },
    { label: 'Stores', items: storeItems },
    { label: 'Reports', items: reportItems },
];

/** INSTRUCTIONS.md's Super Admin Portal (T-017) navigation — S01-S10 + Admin Dashboard/Member/Compensation/Draw management, mirroring AdminSidebar's structure. */
export function SuperAdminSidebar() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {sections.map((section) => (
                    <SidebarGroup key={section.label} className="px-2 py-0">
                        <SidebarGroupLabel>{section.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {section.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
