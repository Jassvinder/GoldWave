import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowUpDown, Eye } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

function renderCellFallback(value: unknown): ReactNode {
    if (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
    ) {
        return String(value);
    }

    return '—';
}

export type DataTableColumn<T> = {
    key: string;
    header: string;
    /** Renders `row[key]` when omitted. */
    render?: (row: T) => ReactNode;
    sortable?: boolean;
    className?: string;
};

export type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    rows: T[];
    rowKey: (row: T, index: number) => string | number;
    /** Detail-page destination — renders an Eye (view) link in the trailing "Actions" column; the row itself is not clickable (T-134). */
    rowHref?: (row: T) => NonNullable<InertiaLinkProps['href']>;
    /** Row click handler for non-navigation interactions (e.g. row selection). */
    onRowClick?: (row: T) => void;
    /** Highlights a row (e.g. the currently selected one) when set. */
    isRowSelected?: (row: T) => boolean;
    /** Extra per-row controls in the trailing "Actions" column, rendered before the `rowHref` Eye link. */
    renderActions?: (row: T) => ReactNode;
    /** Current sort column key + direction, and a handler for a sortable header click — omit to render sort icons inertly. */
    sort?: { key: string; direction: 'asc' | 'desc' };
    onSortChange?: (key: string) => void;
    emptyMessage?: string;
};

/**
 * Shared listing-page table (T-101) — real `<table>` with sortable headers,
 * an optional Eye-icon detail link, and an optional trailing actions column,
 * matching `Docs/Screenshots/3.png`/`4.png`'s pattern. Replaces the
 * `<div>`-row-styled-as-a-list pattern every Super Admin listing page used
 * before this task (see `Docs/ARCHITECTURE.md`'s "Frontend Design System").
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    rowHref,
    onRowClick,
    isRowSelected,
    renderActions,
    sort,
    onSortChange,
    emptyMessage = 'No results found.',
}: DataTableProps<T>) {
    const hasActions = Boolean(renderActions || rowHref);

    return (
        <div className="overflow-hidden rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        {columns.map((column) => (
                            <TableHead
                                key={column.key}
                                className={column.className}
                            >
                                {column.sortable ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onSortChange?.(column.key)
                                        }
                                        className="hover:text-foreground inline-flex items-center gap-1"
                                    >
                                        {column.header}
                                        <ArrowUpDown
                                            className={cn(
                                                'size-3.5',
                                                sort?.key === column.key
                                                    ? 'text-foreground'
                                                    : 'text-muted-foreground/50',
                                            )}
                                        />
                                    </button>
                                ) : (
                                    column.header
                                )}
                            </TableHead>
                        ))}
                        {hasActions && (
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length + (hasActions ? 1 : 0)}
                                className="text-muted-foreground h-24 text-center"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}
                    {rows.map((row, index) => {
                        const href = rowHref?.(row);

                        return (
                            <TableRow
                                key={rowKey(row, index)}
                                className={cn(
                                    onRowClick && 'cursor-pointer',
                                    isRowSelected?.(row) && 'bg-muted/50',
                                )}
                                onClick={
                                    onRowClick
                                        ? () => onRowClick(row)
                                        : undefined
                                }
                            >
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={column.className}
                                    >
                                        {column.render
                                            ? column.render(row)
                                            : renderCellFallback(
                                                  (
                                                      row as Record<
                                                          string,
                                                          unknown
                                                      >
                                                  )[column.key],
                                              )}
                                    </TableCell>
                                ))}
                                {hasActions && (
                                    <TableCell
                                        className="text-right"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <div className="flex items-center justify-end gap-1">
                                            {renderActions?.(row)}
                                            {href && (
                                                <Link
                                                    href={href}
                                                    aria-label="View details"
                                                    title="View details"
                                                    className="text-muted-foreground hover:bg-muted hover:text-foreground inline-flex size-8 items-center justify-center rounded-md"
                                                >
                                                    <Eye className="size-4" />
                                                </Link>
                                            )}
                                        </div>
                                    </TableCell>
                                )}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
