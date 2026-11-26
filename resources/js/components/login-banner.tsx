import * as DialogPrimitive from '@radix-ui/react-dialog';
import {
    BadgeCheck,
    Gem,
    Gift,
    Handshake,
    MapPin,
    Settings,
    ShieldCheck,
    Trophy,
    Vault,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { TodaysRate } from '@/components/dashboard-highlights';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { cn, formatDate } from '@/lib/utils';

export type LoginBannerWinner = {
    group_no: number;
    name: string | null;
    customer_id: string;
    prize: string;
};

export type LoginBannerData = {
    /** New on every login. */
    key: string;
    winners: { month: string; list: LoginBannerWinner[] } | null;
};

const SEEN_KEY = 'goldwave.login-banner-seen';

/** Gold text with the banner's bright-to-deep shine. */
const GOLD_TEXT =
    'bg-gradient-to-b from-[#fff6c8] via-[#f3c54b] to-[#a8740f] bg-clip-text text-transparent';

const FEATURES: { icon: LucideIcon; label: string }[] = [
    { icon: Vault, label: 'Pure Gold Jewellery' },
    { icon: Gem, label: 'Trendy & Traditional Designs' },
    { icon: BadgeCheck, label: 'Hallmarked & Certified' },
    { icon: Settings, label: 'Customised Jewellery' },
    { icon: ShieldCheck, label: 'Best Value & Transparency' },
];

const PROMISES: { icon: LucideIcon; label: string }[] = [
    { icon: Gem, label: 'Elegant Designs' },
    { icon: ShieldCheck, label: '100% Purity' },
    { icon: Handshake, label: 'Customer Satisfaction' },
    { icon: Gift, label: 'Perfect for Every Occasion' },
];

/** Soft out-of-focus lights behind the banner, like the shop-window bokeh of the client's poster. */
const BOKEH = [
    'top-[6%] left-[4%] size-16',
    'top-[18%] right-[6%] size-10',
    'top-[38%] left-[-3%] size-20',
    'top-[52%] right-[-2%] size-14',
    'top-[70%] left-[8%] size-8',
    'bottom-[12%] right-[10%] size-12',
];

/** "1,23,850" — whole rupees when the rate has no paise, else two decimals. */
function rupees(perGram: string, grams: number): string {
    const amount = Number(perGram) * grams;

    return amount.toLocaleString('en-IN', {
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
        maximumFractionDigits: 2,
    });
}

function GoldIcon({
    icon: Icon,
    small,
}: {
    icon: LucideIcon;
    small?: boolean;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full border-2 border-[#e2b54a] bg-gradient-to-b from-[#3a2508] to-[#160d03] text-[#f3c54b] shadow-[0_0_12px_rgba(243,197,75,0.35)]',
                small ? 'size-8' : 'size-9',
            )}
        >
            <Icon className={small ? 'size-4' : 'size-[18px]'} />
        </span>
    );
}

/** A thin gold rule with a diamond in the middle. */
function GoldRule({ className }: { className?: string }) {
    return (
        <div
            className={cn('flex items-center gap-2', className)}
            aria-hidden="true"
        >
            <span className="h-px flex-1 bg-gradient-to-r from-transparent to-[#e2b54a]" />
            <span className="size-1.5 rotate-45 bg-[#f3c54b]" />
            <span className="h-px flex-1 bg-gradient-to-l from-transparent to-[#e2b54a]" />
        </div>
    );
}

/**
 * T-249 (INSTRUCTIONS.md M01) — the welcome banner a member sees once after every login: the client's Gold Waves
 * poster rebuilt in HTML with the app logo, today's saved gold/silver rate (Gold & Silver Rates, shown as entered, no
 * carat) and the latest month's draw winners from Banner Winners (T-248). Closed with the X, Esc or a tap outside.
 */
export function LoginBanner({
    banner,
    rates,
}: {
    banner: LoginBannerData;
    rates: TodaysRate[];
}) {
    // Shown once per login key, so Back to a dashboard restored from browser history doesn't open it again.
    const [open, setOpen] = useState(
        () =>
            typeof window === 'undefined' ||
            window.sessionStorage.getItem(SEEN_KEY) !== banner.key,
    );

    useEffect(() => {
        if (open) {
            window.sessionStorage.setItem(SEEN_KEY, banner.key);
        }
    }, [open, banner.key]);

    const winners = banner.winners;
    const rateDate = rates[0]?.effective_from ?? null;

    return (
        <DialogPrimitive.Root open={open} onOpenChange={setOpen}>
            <DialogPortal>
                <DialogOverlay className="bg-black/85 backdrop-blur-[2px]" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-1/2 left-1/2 z-50 w-[calc(100%-1.5rem)] max-w-[560px] -translate-x-1/2 -translate-y-1/2 rounded-2xl p-[2px] shadow-[0_0_60px_rgba(243,197,75,0.35)] duration-300 focus:outline-none"
                    style={{
                        background:
                            'linear-gradient(160deg,#fff1b8 0%,#c8951f 30%,#6b4508 55%,#e9bd50 80%,#fff1b8 100%)',
                    }}
                >
                    <DialogPrimitive.Close
                        className="absolute -top-3 -right-3 z-20 flex size-10 items-center justify-center rounded-full border-2 border-[#f3c54b] bg-[#140b02] text-[#f3c54b] shadow-[0_0_16px_rgba(243,197,75,0.55)] transition hover:scale-105 hover:bg-[#2a1905] focus-visible:ring-2 focus-visible:ring-[#f3c54b] focus-visible:outline-none sm:-top-4 sm:-right-4"
                        aria-label="Close"
                    >
                        <X className="size-5" />
                    </DialogPrimitive.Close>

                    <div
                        className="relative max-h-[calc(100dvh-3rem)] [scrollbar-width:thin] overflow-x-hidden overflow-y-auto overscroll-contain rounded-[14px] text-[#fbeccb]"
                        style={{
                            background:
                                'radial-gradient(120% 60% at 50% 0%,#4a2e0b 0%,#1c1105 45%,#0c0703 100%)',
                        }}
                    >
                        {/* Background lights */}
                        <div
                            className="pointer-events-none absolute inset-0 overflow-hidden"
                            aria-hidden="true"
                        >
                            {BOKEH.map((position, index) => (
                                <span
                                    key={index}
                                    className={cn(
                                        'absolute rounded-full bg-[#f7c95c] opacity-25 blur-md motion-safe:animate-pulse',
                                        position,
                                    )}
                                    style={{
                                        animationDelay: `${index * 0.6}s`,
                                        animationDuration: '3.5s',
                                    }}
                                />
                            ))}
                        </div>

                        <div className="relative flex flex-col gap-5 px-4 pt-6 pb-5 sm:px-7 sm:pt-7">
                            {/* Header */}
                            <header className="flex flex-col items-center text-center">
                                <div className="font-banner-italic flex w-full items-start justify-between text-sm leading-tight text-[#f6dc9b] italic sm:text-base">
                                    <span className="text-left">
                                        Timeless
                                        <br />
                                        Beauty…
                                    </span>
                                    <span className="text-right">
                                        Lasting
                                        <br />
                                        Value…
                                    </span>
                                </div>
                                <img
                                    src="/Images/Logo.webp"
                                    alt="GoldWave"
                                    width={112}
                                    height={112}
                                    className="-mt-8 size-24 rounded-full drop-shadow-[0_0_18px_rgba(243,197,75,0.55)] sm:size-28"
                                />
                                <DialogPrimitive.Title
                                    className={cn(
                                        'font-banner-serif mt-1 text-4xl leading-none font-bold tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,0.6)] sm:text-5xl',
                                        GOLD_TEXT,
                                    )}
                                >
                                    Gold Waves
                                </DialogPrimitive.Title>
                                <p className="font-banner-serif mt-2 text-[10px] tracking-[0.22em] text-[#f3e2b8] sm:text-xs">
                                    FINE JEWELLERY | TRUST | TRADITION | FOREVER
                                </p>
                                <p
                                    className={cn(
                                        'font-banner-script mt-2 text-3xl leading-tight sm:text-4xl',
                                        GOLD_TEXT,
                                    )}
                                >
                                    Gold that Moves with Your Dreams
                                </p>
                            </header>

                            {/* Rates + features */}
                            <div
                                className={cn(
                                    'grid grid-cols-1 gap-4',
                                    rates.length > 0 &&
                                        'sm:grid-cols-[1fr_auto]',
                                )}
                            >
                                {rates.length > 0 && (
                                    <section
                                        aria-label="Gold and silver price today"
                                        className="relative rounded-xl border-2 border-[#d9a93a] bg-gradient-to-b from-[#fff4d2] via-[#f9e2a4] to-[#eec66c] px-3 pt-6 pb-3 text-[#3b2306] shadow-[0_0_24px_rgba(243,197,75,0.35)]"
                                    >
                                        <h2 className="font-banner-serif absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full border border-[#f3c54b] bg-gradient-to-b from-[#7a1b14] to-[#4c0d09] px-4 py-1 text-xs font-bold whitespace-nowrap text-[#fbe7b5] shadow-md sm:text-sm">
                                            Gold &amp; Silver Price Today
                                        </h2>
                                        <div className="grid grid-flow-col divide-x divide-[#b8862d]/50">
                                            {rates.map((rate) => (
                                                <div
                                                    key={rate.metal}
                                                    className="flex flex-col items-center px-2 text-center"
                                                >
                                                    <span className="font-banner-serif text-sm font-bold">
                                                        {rate.metal === 'gold'
                                                            ? 'Gold'
                                                            : 'Silver'}
                                                    </span>
                                                    <span className="font-banner-serif text-xl font-bold text-[#a3150f] tabular-nums sm:text-2xl">
                                                        ₹
                                                        {rupees(
                                                            rate.rate_per_gram,
                                                            10,
                                                        )}
                                                    </span>
                                                    <span className="text-xs font-medium">
                                                        per 10 gm
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        {rateDate && (
                                            <p className="mt-2 text-center text-[11px] text-[#6b4a14]">
                                                Rate from {formatDate(rateDate)}
                                            </p>
                                        )}
                                    </section>
                                )}

                                <ul
                                    className={cn(
                                        'grid gap-2.5',
                                        rates.length > 0
                                            ? 'grid-cols-2 sm:grid-cols-1 sm:border-l sm:border-[#e2b54a]/50 sm:pl-4'
                                            : 'grid-cols-2',
                                    )}
                                >
                                    {FEATURES.map((feature) => (
                                        <li
                                            key={feature.label}
                                            className="flex items-center gap-2"
                                        >
                                            <GoldIcon
                                                icon={feature.icon}
                                                small
                                            />
                                            <span className="font-banner-italic text-[13px] leading-tight text-[#fbeccb] sm:max-w-[9.5rem]">
                                                {feature.label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Draw winners */}
                            {winners && winners.list.length > 0 && (
                                <section
                                    aria-label="Lucky draw winners"
                                    className="rounded-xl border border-[#e2b54a]/70 bg-black/35 p-3 shadow-[inset_0_0_24px_rgba(243,197,75,0.12)]"
                                >
                                    <div className="mb-2 flex flex-col items-center text-center">
                                        <div className="flex items-center gap-2">
                                            <Trophy className="size-5 text-[#f3c54b]" />
                                            <h2
                                                className={cn(
                                                    'font-banner-serif text-lg font-bold tracking-wide',
                                                    GOLD_TEXT,
                                                )}
                                            >
                                                Lucky Draw Winners
                                            </h2>
                                        </div>
                                        <span className="font-banner-italic text-sm text-[#f6dc9b] italic">
                                            {winners.month}
                                        </span>
                                    </div>
                                    <ul className="flex max-h-56 [scrollbar-width:thin] flex-col gap-1.5 overflow-y-auto pr-1">
                                        {winners.list.map((winner) => (
                                            <li
                                                key={`${winner.group_no}-${winner.customer_id}`}
                                                className="flex items-center gap-3 rounded-lg border border-[#e2b54a]/25 bg-gradient-to-r from-[#3a2508]/80 to-[#1a1004]/80 px-3 py-2"
                                            >
                                                <span className="font-banner-serif flex shrink-0 flex-col items-center rounded-md border border-[#e2b54a]/60 px-2 py-0.5 text-[#f3c54b]">
                                                    <span className="text-[9px] tracking-widest">
                                                        GROUP
                                                    </span>
                                                    <span className="text-base leading-none font-bold">
                                                        {winner.group_no}
                                                    </span>
                                                </span>
                                                <span className="flex min-w-0 flex-1 flex-col">
                                                    <span className="truncate font-semibold text-[#fff3d6]">
                                                        {winner.name ??
                                                            winner.customer_id}
                                                    </span>
                                                    <span className="font-mono text-xs text-[#e7c983]">
                                                        {winner.customer_id}
                                                    </span>
                                                </span>
                                                <span className="font-banner-italic max-w-[40%] text-right text-[13px] leading-tight text-[#f6dc9b] italic">
                                                    {winner.prize}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}

                            {/* Jewellery */}
                            <img
                                src="/Images/banner-jewellery.webp"
                                alt="Gold necklace, jhumka earrings, ring and bangles"
                                width={1024}
                                height={412}
                                loading="eager"
                                className="-mx-4 w-[calc(100%+2rem)] max-w-none sm:-mx-7 sm:w-[calc(100%+3.5rem)]"
                                style={{
                                    maskImage:
                                        'linear-gradient(to bottom,transparent 0%,#000 18%,#000 82%,transparent 100%)',
                                    WebkitMaskImage:
                                        'linear-gradient(to bottom,transparent 0%,#000 18%,#000 82%,transparent 100%)',
                                }}
                            />

                            {/* Address */}
                            <footer className="-mt-3 flex flex-col items-center gap-2 text-center">
                                <div
                                    className="h-[3px] w-full rounded-full bg-gradient-to-r from-transparent via-[#f3c54b] to-transparent"
                                    aria-hidden="true"
                                />
                                <p className="flex items-center gap-2">
                                    <MapPin className="size-7 text-[#f3c54b]" />
                                    <span
                                        className={cn(
                                            'font-banner-italic text-3xl font-semibold italic sm:text-4xl',
                                            GOLD_TEXT,
                                        )}
                                    >
                                        Gold Waves
                                    </span>
                                </p>
                                <p className="font-banner-serif text-sm font-semibold text-[#fff3d6] sm:text-base">
                                    Jalbera Road, Sonda, Ambala City, Haryana
                                </p>
                                <GoldRule className="w-full" />
                                <p className="font-banner-italic text-sm text-[#f3c54b] italic sm:text-base">
                                    Where Elegance Meets Eternal Value
                                </p>
                                <GoldRule className="w-full" />

                                <ul className="mt-1 grid w-full grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-0 sm:divide-x sm:divide-[#e2b54a]/40">
                                    {PROMISES.map((promise) => (
                                        <li
                                            key={promise.label}
                                            className="flex items-center justify-center gap-2 px-1 sm:flex-col sm:gap-1.5"
                                        >
                                            <GoldIcon icon={promise.icon} />
                                            <span className="font-banner-italic text-left text-xs leading-tight text-[#fbeccb] sm:text-center">
                                                {promise.label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </footer>
                        </div>
                    </div>
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}
