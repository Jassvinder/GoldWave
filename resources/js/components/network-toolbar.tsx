import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    onSearch: (customerId: string) => void;
    error?: string;
};

/**
 * Shared by Directs View and Tree View (DOMAIN_LOGIC.md §4.1/§4.2). Back
 * uses plain browser history — every card click is a real Inertia
 * navigation, so this correctly retraces whatever path the viewer actually
 * took, including search jumps. Search is scoped server-side to the
 * viewer's own downline (see DirectsController/TreeController::search) —
 * this component just submits a Customer ID and surfaces the resulting
 * error, it does no authorization itself.
 */
export default function NetworkToolbar({ onSearch, error }: Props) {
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
