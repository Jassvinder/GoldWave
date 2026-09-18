import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
} from '@/components/ui/pagination';
import type { Paginated } from '@/types';

/** Returns page numbers to render, with `null` standing in for an ellipsis. */
function pageWindow(current: number, last: number): (number | null)[] {
    const pages = new Set<number>([1, last, current]);

    for (let offset = -1; offset <= 1; offset++) {
        const page = current + offset;

        if (page >= 1 && page <= last) {
            pages.add(page);
        }
    }

    const sorted = [...pages].sort((a, b) => a - b);
    const withGaps: (number | null)[] = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            withGaps.push(null);
        }

        withGaps.push(page);
    });

    return withGaps;
}

/**
 * Shared listing-page pagination footer (T-101) — numbered pages with an
 * ellipsis for large ranges, plus a "Showing X to Y of Z entries" line,
 * matching `Docs/Screenshots/3.png`/`4.png`'s pattern. Built on the shadcn
 * `ui/pagination.tsx` primitives added in T-100.
 */
export function DataPagination<T>({
    paginated,
    onPageChange,
}: {
    paginated: Paginated<T>;
    onPageChange: (page: number) => void;
}) {
    if (paginated.last_page <= 1) {
        return (
            <p className="text-muted-foreground text-sm">
                Showing {paginated.from ?? 0} to {paginated.to ?? 0} of{' '}
                {paginated.total} entries
            </p>
        );
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-muted-foreground text-sm">
                Showing {paginated.from ?? 0} to {paginated.to ?? 0} of{' '}
                {paginated.total} entries
            </p>

            <Pagination className="mx-0 w-auto">
                <PaginationContent>
                    <PaginationItem>
                        <PaginationLink
                            aria-disabled={paginated.current_page <= 1}
                            className={
                                paginated.current_page <= 1
                                    ? 'pointer-events-none opacity-50'
                                    : 'cursor-pointer'
                            }
                            onClick={(e) => {
                                e.preventDefault();
                                onPageChange(paginated.current_page - 1);
                            }}
                        >
                            <span className="sr-only">Previous</span>‹
                        </PaginationLink>
                    </PaginationItem>

                    {pageWindow(
                        paginated.current_page,
                        paginated.last_page,
                    ).map((page, index) =>
                        page === null ? (
                            <PaginationItem key={`ellipsis-${index}`}>
                                <PaginationEllipsis />
                            </PaginationItem>
                        ) : (
                            <PaginationItem key={page}>
                                <PaginationLink
                                    isActive={page === paginated.current_page}
                                    className="cursor-pointer"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        onPageChange(page);
                                    }}
                                >
                                    {page}
                                </PaginationLink>
                            </PaginationItem>
                        ),
                    )}

                    <PaginationItem>
                        <PaginationLink
                            aria-disabled={
                                paginated.current_page >= paginated.last_page
                            }
                            className={
                                paginated.current_page >= paginated.last_page
                                    ? 'pointer-events-none opacity-50'
                                    : 'cursor-pointer'
                            }
                            onClick={(e) => {
                                e.preventDefault();
                                onPageChange(paginated.current_page + 1);
                            }}
                        >
                            <span className="sr-only">Next</span>›
                        </PaginationLink>
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        </div>
    );
}
