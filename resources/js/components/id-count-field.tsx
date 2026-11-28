import { Users } from 'lucide-react';
import InputError from '@/components/input-error';
import type { PaymentMode, PaymentOptions } from '@/components/payment-mode-picker';
import { Label } from '@/components/ui/label';

const rupees = (value: number) =>
    `₹${value.toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;

/** T-251 — modes allowed when several IDs are paid together: Cash or GPay/UPI only (DOMAIN_LOGIC.md §3.4). */
export const COMBINED_MODES: PaymentMode[] = ['cash', 'upi'];

/** The payment step's options for this many IDs: several IDs keep only Cash and GPay/UPI. */
export function paymentOptionsFor(
    options: PaymentOptions,
    count: number,
): PaymentOptions {
    return count > 1
        ? {
              ...options,
              modes: options.modes.filter((mode) =>
                  COMBINED_MODES.includes(mode),
              ),
          }
        : options;
}

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — "Number of IDs" on a registration or "Add ID": one person, several IDs on the same
 * plan, paid together. Shows the total once a plan is chosen and how the IDs are placed.
 */
export function IdCountField({
    id = 'id_count',
    value,
    max,
    amount,
    onChange,
    error,
    placementNote,
}: {
    id?: string;
    value: number;
    max: number;
    /** The plan's first payment (EMI or one-time amount), or undefined before a plan is chosen. */
    amount?: string | number | null;
    onChange: (value: number) => void;
    error?: string;
    placementNote: string;
}) {
    const each = Number(amount ?? 0);
    const options = Array.from({ length: Math.max(1, max) }, (_, i) => i + 1);

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Number of IDs</Label>
            <select
                id={id}
                className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
            >
                {options.map((count) => (
                    <option key={count} value={count}>
                        {count === 1 ? '1 ID' : `${count} IDs`}
                    </option>
                ))}
            </select>
            {value > 1 && (
                <div className="flex gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                    <Users className="mt-0.5 size-4 shrink-0" />
                    <div className="grid gap-1">
                        <span className="font-semibold">
                            {each > 0
                                ? `${value} IDs × ${rupees(each)} = ${rupees(each * value)}`
                                : `${value} IDs on the same plan`}
                        </span>
                        <span>
                            Paid together in one Cash or GPay/UPI payment. All{' '}
                            {value} IDs become active when it is approved, each
                            with its own Customer ID. {placementNote}
                        </span>
                    </div>
                </div>
            )}
            <InputError message={error} />
        </div>
    );
}
