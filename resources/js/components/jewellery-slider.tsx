import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

export type Slide = { src: string; alt: string };

type Props = {
    slides: Slide[];
    intervalMs?: number;
};

/**
 * T-210 (03-10-2026) — the home page's image-only jewellery slider: no text over the photos (user decision).
 * Cross-fades every 5 s, pauses while the pointer or focus is on it, and stays still for reduced-motion users.
 * Previous/next arrows, no dots (the user's T-130 choice). Only the first image loads eagerly. The jewellery sits on the
 * right of every banner, so images are anchored right when a narrow screen crops them.
 */
export function JewellerySlider({ slides, intervalMs = 5000 }: Props) {
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const count = slides.length;

    useEffect(() => {
        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (paused || count < 2 || reducedMotion) {
            return;
        }

        const timer = window.setTimeout(
            () => setActive((current) => (current + 1) % count),
            intervalMs,
        );

        return () => window.clearTimeout(timer);
    }, [active, paused, count, intervalMs]);

    const go = (delta: number) =>
        setActive((current) => (current + delta + count) % count);

    return (
        <section
            className="relative aspect-[16/9] w-full overflow-hidden rounded-3xl bg-amber-950 shadow-xl ring-1 ring-amber-200 sm:aspect-[5/2]"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            aria-roledescription="carousel"
            aria-label="GoldWave gold jewellery"
        >
            {slides.map((slide, index) => (
                <img
                    key={slide.src}
                    src={slide.src}
                    alt={slide.alt}
                    loading={index === 0 ? 'eager' : 'lazy'}
                    aria-hidden={index !== active}
                    className={cn(
                        'absolute inset-0 size-full object-cover object-right transition-opacity duration-1000',
                        index === active ? 'opacity-100' : 'opacity-0',
                    )}
                />
            ))}

            {count > 1 && (
                <>
                    <SlideButton
                        side="left"
                        label="Previous slide"
                        onClick={() => go(-1)}
                    />
                    <SlideButton
                        side="right"
                        label="Next slide"
                        onClick={() => go(1)}
                    />
                </>
            )}
        </section>
    );
}

function SlideButton({
    side,
    label,
    onClick,
}: {
    side: 'left' | 'right';
    label: string;
    onClick: () => void;
}) {
    const Icon = side === 'left' ? ChevronLeft : ChevronRight;

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className={cn(
                'absolute top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:outline-none sm:size-10',
                side === 'left' ? 'left-3' : 'right-3',
            )}
        >
            <Icon className="size-5 sm:size-6" />
        </button>
    );
}
