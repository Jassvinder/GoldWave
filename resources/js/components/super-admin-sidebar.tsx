import {
    Banknote,
    ClipboardEdit,
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
import { PortalSidebar } from '@/components/portal-sidebar';
import { dashboard } from '@/routes';
import { index as adminUsersIndex } from '@/routes/super-admin/admin-users';
import { audit as compensationAudit } from '@/routes/super-admin/compensation';
import { index as drawManagementIndex } from '@/routes/super-admin/draw-management';
import { index as drawSettingsIndex } from '@/routes/super-admin/draw-settings';
import { index as dummyAssignmentIndex } from '@/routes/super-admin/dummy-entry-assignment';
import { index as dummySettingsIndex } from '@/routes/super-admin/dummy-entry-settings';
import { index as membersIndex } from '@/routes/super-admin/members';
import { index as metalRatesIndex } from '@/routes/super-admin/metal-rates';
import { index as payoutRequestsIndex } from '@/routes/super-admin/payout-requests';
import { index as payoutTdsIndex } from '@/routes/super-admin/payout-tds-settings';
import { index as profileChangeRequestsIndex } from '@/routes/super-admin/profile-change-requests';
import { index as reportsIndex } from '@/routes/super-admin/reports';
import { index as ruleVersionsIndex } from '@/routes/super-admin/rule-versions';
import { index as storeManagementIndex } from '@/routes/super-admin/store-management';
import { index as storeWalletsIndex } from '@/routes/super-admin/store-wallets';
import type { NavGroup, NavItem } from '@/types';

const overviewItems: NavItem[] = [
    { title: 'System Dashboard', href: dashboard(), icon: LayoutGrid },
];

const memberItems: NavItem[] = [
    { title: 'Admin Users', href: adminUsersIndex(), icon: UserCog },
    { title: 'Member Management', href: membersIndex(), icon: Users },
    {
        title: 'Dummy Entry Settings',
        href: dummySettingsIndex(),
        icon: UserPlus,
    },
    {
        title: 'Dummy Entry Assignment',
        href: dummyAssignmentIndex(),
        icon: UserCheck,
    },
];

const compensationItems: NavItem[] = [
    { title: 'Rule Versions', href: ruleVersionsIndex(), icon: Percent },
    {
        title: 'Compensation Audit',
        href: compensationAudit(),
        icon: ClipboardList,
    },
];

const drawItems: NavItem[] = [
    { title: 'Draw Settings', href: drawSettingsIndex(), icon: Settings2 },
    { title: 'Draw Management', href: drawManagementIndex(), icon: Trophy },
];

const requestItems: NavItem[] = [
    { title: 'Payout Requests', href: payoutRequestsIndex(), icon: Wallet },
    {
        title: 'Profile Change Requests',
        href: profileChangeRequestsIndex(),
        icon: ClipboardEdit,
    },
];

const settingsItems: NavItem[] = [
    { title: 'Gold & Silver Rates', href: metalRatesIndex(), icon: Coins },
    { title: 'Payout & TDS Settings', href: payoutTdsIndex(), icon: Banknote },
];

const storeItems: NavItem[] = [
    {
        title: 'Store Management',
        href: storeManagementIndex(),
        icon: StoreIcon,
    },
    { title: 'Store Wallets', href: storeWalletsIndex(), icon: Wallet },
];

const reportItems: NavItem[] = [
    { title: 'Reports', href: reportsIndex(), icon: FileSpreadsheet },
];

const sections: NavGroup[] = [
    { label: 'Overview', items: overviewItems },
    { label: 'Members', items: memberItems },
    { label: 'Compensation', items: compensationItems },
    { label: 'Draw', items: drawItems },
    { label: 'Requests', items: requestItems },
    { label: 'Settings', items: settingsItems },
    { label: 'Stores', items: storeItems },
    { label: 'Reports', items: reportItems },
];

/** INSTRUCTIONS.md's Super Admin Portal (T-017) navigation — S01-S10 + Admin Dashboard/Member/Compensation/Draw management. Renders via the shared `PortalSidebar` (T-100). */
export function SuperAdminSidebar() {
    return <PortalSidebar sections={sections} />;
}
