import { usePage } from '@inertiajs/react';
import { HelpGuide } from '@/components/help-guide';

/** T-244 — the Super Admin / company Admin guide; Super-Admin-only topics are left out for the company Admin. */
export default function StaffHelp() {
    const role = (usePage().props.auth as { user?: { role?: string } }).user
        ?.role;

    return <HelpGuide portal="staff" isSuperAdmin={role === 'super_admin'} />;
}
