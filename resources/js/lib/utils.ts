import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Renders a backend ISO date/datetime string in the project's required
 * Indian DD-MM-YYYY display format. Leaves non-ISO-date strings (e.g. a
 * "YYYY-MM" month value) untouched, since only the leading YYYY-MM-DD
 * portion is matched.
 */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;

    return `${day}-${month}-${year}`;
}
