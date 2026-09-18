import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <img
                src="/Images/Logo.png"
                alt={String(name)}
                className="size-9 shrink-0 rounded-full object-cover"
            />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="text-sidebar-foreground mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
