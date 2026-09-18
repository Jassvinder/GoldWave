import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';

/** Header-placed avatar + dropdown (T-100) — replaces NavUser's sidebar-footer placement, matching the reference design's header-only avatar. */
export function NavUserMenu() {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="hover:bg-accent hover:text-accent-foreground flex items-center gap-2 rounded-md px-2 py-1.5 outline-hidden"
                data-test="header-user-menu-button"
            >
                <UserInfo user={auth.user} />
                <ChevronDown className="ml-1 size-4 shrink-0" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-56 rounded-lg"
                align="end"
                side="bottom"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
