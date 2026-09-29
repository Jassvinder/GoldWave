import {
    Banknote,
    Bell,
    ClipboardEdit,
    ClipboardList,
    Coins,
    FileSpreadsheet,
    Gem,
    LayoutGrid,
    LayoutTemplate,
    Percent,
    Scale,
    Settings2,
    ShieldCheck,
    Store as StoreIcon,
    Trophy,
    Truck,
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
import { index as earningsVerificationIndex } from '@/routes/super-admin/earnings-verification';
import { index as drawManagementIndex } from '@/routes/super-admin/draw-management';
import { index as drawSettingsIndex } from '@/routes/super-admin/draw-settings';
import { index as dummyAssignmentIndex } from '@/routes/super-admin/dummy-entry-assignment';
import { index as dummySettingsIndex } from '@/routes/super-admin/dummy-entry-settings';
import { index as financialSummaryIndex } from '@/routes/super-admin/financial-summary';
import { index as landingHeroIndex } from '@/routes/super-admin/landing-hero';
import { index as membersIndex } from '@/routes/super-admin/members';
import { index as notificationsIndex } from '@/routes/super-admin/notifications';
import { index as metalRatesIndex } from '@/routes/super-admin/metal-rates';
import { index as payoutRequestsIndex } from '@/routes/super-admin/payout-requests';
import { index as payoutTdsIndex } from '@/routes/super-admin/payout-tds-settings';
import { index as profileChangeRequestsIndex } from '@/routes/super-admin/profile-change-requests';
import { index as companyDeliveriesIndex } from '@/routes/super-admin/company-deliveries';
import { index as companyWalletIndex } from '@/routes/super-admin/company-wallet';
import { index as rateBookingRequestsIndex } from '@/routes/super-admin/rate-booking-requests';
import { index as reportsIndex } from '@/routes/super-admin/reports';
import { index as restockShipmentsIndex } from '@/routes/super-admin/restock-shipments';
import { index as ruleVersionsIndex } from '@/routes/super-admin/rule-versions';
import { index as storeManagementIndex } from '@/routes/super-admin/store-management';
import { index as storeWalletsIndex } from '@/routes/super-admin/store-wallets';
import type { NavGroup, NavItem } from '@/types';

const overviewItems: NavItem[] = [
    { title: 'System Dashboard', href: dashboard(), icon: LayoutGrid },
    {
        title: 'Financial Summary',
        href: financialSummaryIndex(),
        icon: Scale,
    },
    { title: 'Notifications', href: notificationsIndex(), icon: Bell },
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
    {
        title: 'Earnings Verification',
        href: earningsVerificationIndex(),
        icon: ShieldCheck,
    },
];

const drawItems: NavItem[] = [
    { title: 'Draw Settings', href: drawSettingsIndex(), icon: Settings2 },
    { title: 'Draw Management', href: drawManagementIndex(), icon: Trophy },
];

const requestItems: NavItem[] = [
    { title: 'Payout Requests', href: payoutRequestsIndex(), icon: Wallet },
    {
        title: 'Rate Booking Requests',
        href: rateBookingRequestsIndex(),
        icon: Coins,
    },
    {
        title: 'Profile Change Requests',
        href: profileChangeRequestsIndex(),
        icon: ClipboardEdit,
    },
];

const settingsItems: NavItem[] = [
    { title: 'Gold & Silver Rates', href: metalRatesIndex(), icon: Coins },
    { title: 'Payout & TDS Settings', href: payoutTdsIndex(), icon: Banknote },
    {
        title: 'Landing Page Hero',
        href: landingHeroIndex(),
        icon: LayoutTemplate,
    },
];

const storeItems: NavItem[] = [
    {
        title: 'Store Management',
        href: storeManagementIndex(),
        icon: StoreIcon,
    },
    {
        title: 'Company Plan Deliveries',
        href: companyDeliveriesIndex(),
        icon: Gem,
    },
    { title: 'Store Wallets', href: storeWalletsIndex(), icon: Wallet },
    {
        title: 'Restock Shipments',
        href: restockShipmentsIndex(),
        icon: Truck,
    },
    {
        title: 'Company Wallet',
        href: companyWalletIndex(),
        icon: Banknote,
    },
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
