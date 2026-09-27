import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

export type HeroSlide = { src: string; alt: string };

type Props = {
    slides: HeroSlide[];
    /** Content overlaid on every slide (headline, copy, CTAs). */
    children: ReactNode;
    intervalMs?: number;
};

/**
 * Full-width, auto-advancing hero image slider (T-130). Cross-fades between slides, pauses while the pointer is over it
 * (or focus is inside it), has previous/next arrows and — by the user's explicit choice — no dot indicators.
 * Only the first slide loads eagerly; the rest are lazy-loaded so the page's first paint isn't held up by them.
 */
export function HeroSlider({ slides, children, intervalMs = 5000 }: Props) {
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
            className="relative isolate w-full overflow-hidden bg-neutral-900"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            aria-roledescription="carousel"
            aria-label="GoldWave jewellery"
        >
            {slides.map((slide, index) => (
                <img
                    key={slide.src}
                    src={slide.src}
                    alt={slide.alt}
                    loading={index === 0 ? 'eager' : 'lazy'}
                    fetchPriority={index === 0 ? 'high' : 'auto'}
                    aria-hidden={index !== active}
                    className={`absolute inset-0 -z-10 size-full object-cover transition-opacity duration-1000 ${
                        index === active ? 'opacity-100' : 'opacity-0'
                    }`}
                />
            ))}

            {/* Darkening gradient so the overlaid copy stays readable over any photo. */}
            <div className="absolute inset-0 -z-10 bg-gradient-to-r from-black/70 via-black/40 to-black/10" />

            <div className="mx-auto flex min-h-[26rem] w-full max-w-6xl flex-col justify-center gap-6 px-4 py-16 sm:min-h-[32rem] lg:px-16">
                {children}
            </div>

            {count > 1 && (
                <>
                    <button
                        type="button"
                        onClick={() => go(-1)}
                        aria-label="Previous slide"
                        className="absolute top-1/2 left-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-sm transition hover:bg-black/60"
                    >
                        <ChevronLeft className="size-6" />
                    </button>
                    <button
                        type="button"
                        onClick={() => go(1)}
                        aria-label="Next slide"
                        className="absolute top-1/2 right-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-sm transition hover:bg-black/60"
                    >
                        <ChevronRight className="size-6" />
                    </button>
                </>
            )}
        </section>
    );
}
