import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import type { NavGroup } from '@/types';

/**
 * Shared shell for all three portal sidebars (Member/Admin/Super Admin) — T-100.
 * Each caller only owns its own `sections` nav data; the collapsible-group
 * behavior, logo, and structure live here once instead of being hand-copied
 * three times (see `Docs/ARCHITECTURE.md`'s "Frontend Design System" section).
 */
export function PortalSidebar({ sections }: { sections: NavGroup[] }) {
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
                    <Collapsible
                        key={section.label}
                        defaultOpen
                        className="group/collapsible"
                    >
                        <SidebarGroup className="px-2 py-0">
                            <SidebarGroupLabel asChild>
                                <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between">
                                    <span>{section.label}</span>
                                    <ChevronDown className="size-3.5 shrink-0 transition-transform group-data-[state=closed]/collapsible:-rotate-90" />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarMenu>
                                    {section.items.map((item) => (
                                        <SidebarMenuItem key={item.title}>
                                            <SidebarMenuButton
                                                asChild
                                                isActive={isCurrentUrl(
                                                    item.href,
                                                )}
                                                tooltip={{
                                                    children: item.title,
                                                }}
                                            >
                                                <Link href={item.href} prefetch>
                                                    {item.icon && <item.icon />}
                                                    <span>{item.title}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                ))}
            </SidebarContent>
        </Sidebar>
    );
}
