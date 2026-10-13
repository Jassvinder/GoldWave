import { AlertCircle } from 'lucide-react';

/**
 * T-219 (13-10-2026) — shown next to a form's submit button when the server refused it, listing every message, so a
 * refusal is never silent (e.g. an error on a field that is hidden or far up the page).
 */
export default function FormErrorSummary({
    errors,
}: {
    errors: Partial<Record<string, string>>;
}) {
    const messages = [...new Set(Object.values(errors).filter(Boolean))];

    if (messages.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            className="border-destructive/40 bg-destructive/10 text-destructive flex gap-2 rounded-md border p-3 text-sm"
        >
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            <div className="grid gap-1">
                <p className="font-medium">
                    Please fix the following and try again:
                </p>
                <ul className="list-disc pl-4">
                    {messages.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

/** T-219 — why a submit button is disabled, when the reason is the sponsor code. */
export function SponsorRequiredHint({ status }: { status: string }) {
    if (status === 'valid') {
        return null;
    }

    return (
        <p className="text-muted-foreground -mt-3 text-center text-xs">
            {status === 'checking'
                ? 'Checking the sponsor code…'
                : 'Enter a valid sponsor code above to continue.'}
        </p>
    );
}
