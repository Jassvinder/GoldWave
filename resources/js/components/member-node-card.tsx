import { Link } from '@inertiajs/react';
import { useState } from 'react';

export type NodeMember = {
    id: number;
    name: string | null;
    customer_id: string | null;
    status: string;
    gender: string | null;
    photo_url: string | null;
    sponsor_name: string | null;
};

type Props = {
    member: NodeMember;
    /** Omit to render a non-clickable card (e.g. Directs View's selected member). */
    href?: string;
    className?: string;
};

/**
 * T-119 (19-09-2026) — the one member card shared by Tree View and Directs
 * View (per `Docs/Screenshots/TreeView.png`): circular avatar, bold full
 * name, muted Customer ID, "Sponsored by". The card itself stays
 * rectangular; only the avatar inside is a circle (the earlier "no circular
 * node designs" rule applied to whole nodes, superseded for the avatar by
 * the user's own mockup).
 *
 * Avatar: the member's uploaded photo when there is one and it loads,
 * otherwise a gender placeholder image — female = pink, everything else
 * (male, "other", or gender not recorded) = the male/default one; there is
 * never a bare icon (20-09-2026, user-reported). A small red
 * dot at the card's top-right corner marks a member who is not active.
 */
export function MemberNodeCard({ member, href, className = '' }: Props) {
    const inactive = member.status !== 'active';
    const base =
        'bg-card relative flex w-48 items-center gap-2.5 rounded-md border p-2.5 text-left shadow-sm';
    const classes = `${base} ${href ? 'hover:border-primary transition-colors' : ''} ${className}`;

    const body = (
        <>
            <Avatar member={member} />
            <div className="min-w-0 flex-1">
                <div className="text-sm leading-tight font-semibold break-words">
                    {member.name ?? 'Unnamed member'}
                </div>
                <div className="text-muted-foreground text-xs">
                    {member.customer_id ?? '—'}
                </div>
                <div className="text-muted-foreground text-xs">
                    Sponsored by : {member.sponsor_name ?? '-'}
                </div>
            </div>
            {inactive && (
                <span
                    title="Inactive"
                    className="absolute top-2 right-2 size-3 rounded-full bg-red-500"
                />
            )}
        </>
    );

    return href ? (
        <Link href={href} className={classes}>
            {body}
        </Link>
    ) : (
        <div className={classes}>{body}</div>
    );
}

function Avatar({ member }: { member: NodeMember }) {
    const [photoFailed, setPhotoFailed] = useState(false);
    const placeholder =
        member.gender === 'female'
            ? '/Images/avtar_female.webp'
            : '/Images/avtar_male.webp';
    const src =
        member.photo_url && !photoFailed ? member.photo_url : placeholder;

    return (
        <img
            src={src}
            alt=""
            onError={() => setPhotoFailed(true)}
            className="size-12 shrink-0 rounded-full object-cover"
        />
    );
}
