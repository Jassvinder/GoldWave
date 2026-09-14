import { Link } from '@inertiajs/react';
import {
    Award,
    Bell,
    Banknote,
    Dices,
    FileEdit,
    FileText,
    Gem,
    HandCoins,
    LayoutGrid,
    Network,
    Receipt,
    TrendingUp,
    User,
    Users,
    WalletCards,
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
import { show as showMembership } from '@/routes/member/membership';
import { index as bookerIndex } from '@/routes/member/booster';
import { index as changeRequestsIndex } from '@/routes/member/change-requests';
import { show as showDirects } from '@/routes/member/directs';
import { index as drawIndex } from '@/routes/member/draw';
import { index as emiIndex } from '@/routes/member/emi';
import { index as levelIncomeIndex } from '@/routes/member/level-income';
import { index as notificationsIndex } from '@/routes/member/notifications';
import { index as pairRewardIndex } from '@/routes/member/pair-reward';
import { index as paymentHistoryIndex } from '@/routes/member/payment-history';
import { index as payoutIndex } from '@/routes/member/payout';
import { create as createPendingProfile } from '@/routes/member/pending-profile';
import { show as showProfile } from '@/routes/member/profile';
import { index as reportsIndex } from '@/routes/member/reports';
import { show as showTree } from '@/routes/member/tree';
import { index as walletIndex } from '@/routes/member/wallet';
import type { NavItem } from '@/types';

type NavGroup = {
    label: string;
    items: NavItem[];
};

const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        label: 'Profile',
        items: [
            { title: 'My Profile', href: showProfile(), icon: User },
            {
                title: 'Complete Profile',
                href: createPendingProfile(),
                icon: FileEdit,
            },
            {
                title: 'Change Requests',
                href: changeRequestsIndex(),
                icon: FileText,
            },
        ],
    },
    {
        label: 'Membership & Payments',
        items: [
            { title: 'Membership Plan', href: showMembership(), icon: Gem },
            { title: 'EMI Schedule', href: emiIndex(), icon: Receipt },
            {
                title: 'Payment History',
                href: paymentHistoryIndex(),
                icon: Banknote,
            },
        ],
    },
    {
        label: 'My Network',
        items: [
            { title: 'Directs View', href: showDirects(), icon: Users },
            { title: 'Tree View', href: showTree(), icon: Network },
        ],
    },
    {
        label: 'Income',
        items: [
            {
                title: 'Level Income',
                href: levelIncomeIndex(),
                icon: TrendingUp,
            },
            { title: 'Pair/Reward', href: pairRewardIndex(), icon: Award },
            { title: 'Income Booster', href: bookerIndex(), icon: Award },
        ],
    },
    {
        label: 'Draw & Wallet',
        items: [
            { title: 'Monthly Draw', href: drawIndex(), icon: Dices },
            { title: 'Wallet', href: walletIndex(), icon: WalletCards },
            { title: 'Payout', href: payoutIndex(), icon: HandCoins },
        ],
    },
    {
        label: 'More',
        items: [
            { title: 'Reports', href: reportsIndex(), icon: FileText },
            {
                title: 'Notifications',
                href: notificationsIndex(),
                icon: Bell,
            },
        ],
    },
];

export function MemberSidebar() {
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
                {navGroups.map((group) => (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => (
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
