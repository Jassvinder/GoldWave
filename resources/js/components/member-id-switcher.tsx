import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, IdCard } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { switchId } from '@/routes/member';

type MemberIdOption = {
    id: number;
    customer_id: string | null;
    plan: string | null;
    selected: boolean;
};

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — header "Switch ID" for a person who holds two or more active IDs. Shows the ID every
 * member page is showing right now; picking another one switches all pages to it and opens its dashboard. Hidden for
 * everyone with a single ID (the shared `memberIdSwitcher` prop is null).
 */
export function MemberIdSwitcher() {
    const ids = usePage().props.memberIdSwitcher as
        | MemberIdOption[]
        | null
        | undefined;

    if (!ids || ids.length < 2) {
        return null;
    }

    const current = ids.find((id) => id.selected) ?? ids[0];

    const choose = (option: MemberIdOption) => {
        if (option.selected) {
            return;
        }

        router.post(switchId.url(), { member_id: option.id });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="hover:bg-accent hover:text-accent-foreground flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm font-semibold outline-hidden"
                aria-label={`Current ID ${current.customer_id ?? ''}. Switch ID`}
            >
                <IdCard className="text-muted-foreground size-4 shrink-0" />
                <span className="max-w-28 truncate sm:max-w-none">
                    {current.customer_id}
                </span>
                <ChevronDown className="size-4 shrink-0" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[70vh] w-64 overflow-y-auto"
            >
                <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                    Your IDs ({ids.length}) — choose one to work on
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {ids.map((option) => (
                    <DropdownMenuItem
                        key={option.id}
                        onSelect={() => choose(option)}
                        className="flex cursor-pointer items-center gap-2"
                    >
                        <div className="flex min-w-0 flex-1 flex-col">
                            <span className="font-semibold">
                                {option.customer_id}
                            </span>
                            {option.plan && (
                                <span className="text-muted-foreground truncate text-xs">
                                    {option.plan}
                                </span>
                            )}
                        </div>
                        {option.selected && (
                            <Check className="text-primary size-4 shrink-0" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
