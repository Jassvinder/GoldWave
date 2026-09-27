import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    onSearch: (customerId: string) => void;
    error?: string;
    /** Where the logged-in member's own root view lives; null hides the button (e.g. Super Admin has no own root). */
    rootHref?: string | null;
};

/**
 * Shared by Directs View and Tree View (DOMAIN_LOGIC.md §4.1/§4.2). Go to
 * Root (T-123) jumps straight to the logged-in member's own root view in one
 * step, regardless of how deep the viewer navigated. Back
 * uses plain browser history — every card click is a real Inertia
 * navigation, so this correctly retraces whatever path the viewer actually
 * took, including search jumps. Search is scoped server-side to the
 * viewer's own downline (see DirectsController/TreeController::search) —
 * this component just submits a Customer ID and surfaces the resulting
 * error, it does no authorization itself.
 */
export default function NetworkToolbar({ onSearch, error, rootHref }: Props) {
    const [value, setValue] = useState('');

    function submit(event: React.FormEvent) {
        event.preventDefault();
        if (value.trim()) {
            onSearch(value.trim());
        }
    }

    return (
        <div className="flex flex-wrap items-center gap-3">
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => window.history.back()}
            >
                ← Back
            </Button>
            {rootHref && (
                <Button asChild type="button" variant="outline" size="sm">
                    <Link href={rootHref}>⌂ Go to Root</Link>
                </Button>
            )}
            <form onSubmit={submit} className="flex items-center gap-2">
                <Input
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    placeholder="Search Customer ID (e.g. GWL05)"
                    className="w-56"
                />
                <Button type="submit" size="sm" variant="secondary">
                    Search
                </Button>
            </form>
            {error && <span className="text-destructive text-sm">{error}</span>}
        </div>
    );
}
