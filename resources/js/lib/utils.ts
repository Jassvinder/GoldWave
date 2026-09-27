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

/** Human label for a `members.gender` value (male/female/other); '—' when not recorded. */
export function formatGender(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return value.charAt(0).toUpperCase() + value.slice(1);
}

/** `DD-MM-YYYY, hh:mm AM/PM` (the project's Indian date format) from an ISO timestamp; '—' when missing. */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const pad = (n: number) => String(n).padStart(2, '0');
    const hours = date.getHours();
    const hour12 = hours % 12 === 0 ? 12 : hours % 12;

    return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}, ${pad(hour12)}:${pad(date.getMinutes())} ${hours < 12 ? 'AM' : 'PM'}`;
}

/** "just now", "5 min ago", "3 hours ago", "yesterday", then the DD-MM-YYYY date for anything older than a week. */
export function timeAgo(
    value: string | null | undefined,
    now: Date = new Date(),
): string {
    if (!value) {
        return '';
    }

    const then = new Date(value);

    if (Number.isNaN(then.getTime())) {
        return '';
    }

    const seconds = Math.max(
        0,
        Math.floor((now.getTime() - then.getTime()) / 1000),
    );
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 45) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${Math.max(1, minutes)} min ago`;
    }

    if (hours < 24) {
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }

    if (days === 1) {
        return 'yesterday';
    }

    if (days < 7) {
        return `${days} days ago`;
    }

    return formatDateTime(value).split(',')[0];
}
