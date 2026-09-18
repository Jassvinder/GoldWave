import { Bell, Search } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavUserMenu } from '@/components/nav-user-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Shared portal header (T-100) — replaces `AppSidebarHeader`'s bare
 * trigger+breadcrumbs with the reference design's search/bell/avatar row.
 * The search input is presentational only for now (no backend search
 * endpoint exists yet); the bell has no live unread count wired in yet
 * either — both are placeholders a later task can wire up without
 * touching this component's layout.
 */
export function PortalHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center justify-between gap-4 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="hidden flex-1 justify-center px-4 sm:flex">
                <div className="relative w-full max-w-sm">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        placeholder="Search members, ID, mobile..."
                        className="pl-8"
                    />
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Notifications"
                    className="relative"
                >
                    <Bell className="size-5" />
                </Button>
                <NavUserMenu />
            </div>
        </header>
    );
}
