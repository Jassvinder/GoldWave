import { Link } from '@inertiajs/react';
import {
    Boxes,
    FileText,
    History,
    LayoutGrid,
    ShoppingCart,
    Store as StoreIcon,
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
import { index as inventoryIndex } from '@/routes/admin/inventory';
import { show as showProfile } from '@/routes/admin/profile';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as salesIndex } from '@/routes/admin/sales';
import { index as transactionsIndex } from '@/routes/admin/transactions';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Store Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Store Profile', href: showProfile(), icon: StoreIcon },
    { title: 'Repurchases / Sales', href: salesIndex(), icon: ShoppingCart },
    { title: 'Inventory', href: inventoryIndex(), icon: Boxes },
    { title: 'Store Transactions', href: transactionsIndex(), icon: History },
    { title: 'Store Reports', href: reportsIndex(), icon: FileText },
];

/** INSTRUCTIONS.md's Admin / Store Owner Portal (T-016) navigation — A01-A06, mirroring MemberSidebar's structure. */
export function AdminSidebar() {
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
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Store Operations</SidebarGroupLabel>
                    <SidebarMenu>
                        {navItems.map((item) => (
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
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
