import { Link } from '@inertiajs/react';
import { home } from '@/routes';

/** GoldWave logo + name linking back to the home page — shown above the card on the public pre-login pages (Join, Registration Status). */
export function PublicLogoLink() {
    return (
        <Link
            href={home()}
            className="flex items-center justify-center gap-2 self-center"
            aria-label="GoldWave home"
        >
            <img
                src="/Images/Logo.webp"
                alt="GoldWave"
                className="size-14 rounded-full object-cover"
            />
            <span className="text-xl font-semibold">GoldWave</span>
        </Link>
    );
}
