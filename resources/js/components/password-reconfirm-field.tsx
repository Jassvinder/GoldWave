import { LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import PasswordInput from '@/components/password-input';

/**
 * Save-side password re-entry on password-gated Super Admin pages (`password.fresh` middleware, ARCHITECTURE.md
 * "Password re-entry for sensitive Super Admin actions"). Hidden until the server answers a save with a
 * `confirm_password` error (more than 5 minutes since the last confirmation); then it asks for the password right
 * inside the form, so nothing the Super Admin filled in is lost. The form sends `confirm_password` with its next save.
 */
export default function PasswordReconfirmField({
    id,
    value,
    error,
    onChange,
}: {
    id: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    if (!error && value === '') {
        return null;
    }

    return (
        <div className="grid gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950">
            <Label
                htmlFor={id}
                className="flex items-center gap-2 text-amber-900 dark:text-amber-200"
            >
                <LockKeyhole className="size-4" />
                Your password
            </Label>
            <PasswordInput
                id={id}
                autoComplete="current-password"
                autoFocus
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="bg-background"
            />
            <InputError message={error} />
        </div>
    );
}
