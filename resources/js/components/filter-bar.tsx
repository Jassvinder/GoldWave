import { RotateCcw, Search as SearchIcon } from 'lucide-react';
import type { FormEventHandler, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type FilterBarProps = {
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    onSubmit: FormEventHandler;
    onReset: () => void;
    /** `<Select>` filter dropdowns, rendered between the search input and the action buttons. */
    children?: ReactNode;
};

/**
 * Shared search + filter bar (T-101) — search input, optional `<Select>`
 * filter children, Reset and Search buttons, matching
 * `Docs/Screenshots/3.png`/`4.png`'s pattern.
 */
export function FilterBar({
    search,
    onSearchChange,
    searchPlaceholder = 'Search...',
    onSubmit,
    onReset,
    children,
}: FilterBarProps) {
    return (
        <form
            onSubmit={onSubmit}
            className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <div className="relative min-w-0 flex-1 sm:min-w-[16rem]">
                <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                <Input
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder={searchPlaceholder}
                    className="pl-8"
                />
            </div>

            {children}

            <div className="flex shrink-0 gap-2">
                <Button type="button" variant="outline" onClick={onReset}>
                    <RotateCcw className="size-4" />
                    Reset
                </Button>
                <Button type="submit">
                    <SearchIcon className="size-4" />
                    Search
                </Button>
            </div>
        </form>
    );
}
