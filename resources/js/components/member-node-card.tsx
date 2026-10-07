import { Link } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

export type NodeMember = {
    id: number;
    name: string | null;
    customer_id: string | null;
    status: string;
    gender: string | null;
    photo_url: string | null;
    sponsor_name: string | null;
    left_count: number;
    right_count: number;
};

type Props = {
    member: NodeMember;
    /** Omit to render a non-clickable card (e.g. Directs View's selected member). */
    href?: string;
    className?: string;
};

/**
 * The one member card shared by Tree View and Directs View.
 *
 * T-208 (02-10-2026) — compact redesign from `Docs/Screenshots/MemberCard.png`; the client found the T-119 card too
 * big. 160px wide (was 192px): a gold-bordered card with a round gold-ringed avatar (kept inside the card to save space), the Customer ID in
 * a small chip **under the avatar** (the user's change to the mockup), then the name, "Sp: <sponsor>" and the member's
 * Left/Right Binary team size.
 *
 * Avatar: the uploaded photo when it loads, otherwise the gender placeholder (female = pink, everything else = the
 * male/default one); never a bare icon (20-09-2026). A small red dot at the top-right marks a member who is not active.
 */
export function MemberNodeCard({ member, href, className = '' }: Props) {
    const inactive = member.status !== 'active';
    const classes = cn(
        'dark:bg-card relative flex items-stretch gap-1.5 rounded-xl border border-amber-400 bg-white py-1 pr-2 pl-1.5 text-left shadow-sm',
        'w-40', // keep in step with Tree View's Empty slot
        href &&
            'transition-shadow hover:shadow-md hover:ring-2 hover:ring-amber-200',
        className,
    );

    const body = (
        <>
            <div className="flex w-11 shrink-0 flex-col items-center">
                <Avatar member={member} />
                <span
                    title={member.customer_id ?? undefined}
                    className="max-w-full truncate rounded bg-amber-100 px-1 text-[10px] leading-4 font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                >
                    {member.customer_id ?? '—'}
                </span>
            </div>
            <div className="flex min-w-0 flex-1 flex-col justify-center gap-0.5">
                <div
                    className="truncate text-xs leading-tight font-semibold text-slate-900 dark:text-slate-100"
                    title={member.name ?? undefined}
                >
                    {member.name ?? 'Unnamed member'}
                </div>
                <div
                    className="text-muted-foreground flex items-center gap-0.5 truncate text-[10px] leading-tight"
                    title={
                        member.sponsor_name
                            ? `Sponsor: ${member.sponsor_name}`
                            : undefined
                    }
                >
                    <UserRound className="size-2.5 shrink-0" />
                    <span className="truncate">
                        <span className="font-semibold">Sp:</span>{' '}
                        {member.sponsor_name ?? '—'}
                    </span>
                </div>
                <div className="flex gap-1 text-[10px] leading-tight font-medium">
                    <span
                        className="rounded bg-sky-50 px-1 text-sky-700 dark:bg-sky-950 dark:text-sky-300"
                        title="Left team"
                    >
                        L {member.left_count}
                    </span>
                    <span
                        className="rounded bg-fuchsia-50 px-1 text-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-300"
                        title="Right team"
                    >
                        R {member.right_count}
                    </span>
                </div>
            </div>
            {inactive && (
                <span
                    title="Inactive"
                    className="absolute top-1.5 right-1.5 size-2 rounded-full bg-red-500"
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
            className="size-9 rounded-full border-2 border-amber-400 bg-white object-cover"
        />
    );
}
