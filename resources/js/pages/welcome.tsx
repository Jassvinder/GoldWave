import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, Gem, TrendingUp } from 'lucide-react';
import { HeroSlider, type HeroSlide } from '@/components/hero-slider';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { login as memberLogin } from '@/routes/member';
import { show as registerShow } from '@/routes/registration';

const FEATURES = [
    {
        icon: Gem,
        title: 'Flexible EMI plans',
        description:
            'Join a plan and pay in easy monthly instalments toward real gold or silver jewellery.',
    },
    {
        icon: TrendingUp,
        title: 'Level & Pair rewards',
        description:
            'Earn rewards as your own network of members grows alongside you.',
    },
    {
        icon: CalendarClock,
        title: 'Monthly lucky draw',
        description:
            'Every eligible group takes part in a transparent monthly draw with a real winner each month.',
    },
];

/** Placeholder slides (T-130) — abstract gold/silver gradients. Replace the files in `public/Images/hero/` with real jewellery photos (keep the names, or edit the paths here). */
const HERO_SLIDES: HeroSlide[] = [1, 2, 3, 4, 5].map((n) => ({
    src: `/Images/hero/slide-${n}.webp`,
    alt: 'GoldWave gold and silver jewellery',
}));

type Hero = {
    headline: string;
    subtext: string;
    cta_primary_label: string;
    cta_secondary_label: string;
};

/** T-104 — public landing page at `/`, replacing the untouched Laravel starter-kit `welcome.tsx`. Hero copy made Super-Admin-editable by T-115 (19-09-2026); hero is a full-width image slider since T-130 (20-09-2026). */
export default function Welcome({ hero }: { hero: Hero }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="GoldWave — Gold & Silver Jewellery Membership" />

            <div className="bg-background text-foreground min-h-screen">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between p-4">
                    <div className="flex items-center gap-2">
                        <img
                            src="/Images/Logo.webp"
                            alt="GoldWave"
                            className="size-10 rounded-full object-cover"
                        />
                        <span className="text-lg font-semibold">GoldWave</span>
                    </div>

                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Dashboard</Link>
                        </Button>
                    ) : (
                        <div className="flex items-center gap-2">
                            <Button variant="outline" asChild>
                                <Link href={memberLogin()}>Member Login</Link>
                            </Button>
                            <Button asChild>
                                <Link href={registerShow()}>Join Now</Link>
                            </Button>
                        </div>
                    )}
                </header>

                <HeroSlider slides={HERO_SLIDES}>
                    <h1 className="max-w-2xl text-4xl font-bold tracking-tight text-white sm:text-5xl">
                        {hero.headline}
                    </h1>
                    <p className="max-w-xl text-lg text-white/85">
                        {hero.subtext}
                    </p>
                    {!auth.user && (
                        <div className="flex flex-wrap gap-3">
                            <Button size="lg" asChild>
                                <Link href={registerShow()}>
                                    {hero.cta_primary_label}
                                </Link>
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                className="border-white/60 bg-transparent text-white hover:bg-white/15 hover:text-white"
                                asChild
                            >
                                <Link href={memberLogin()}>
                                    {hero.cta_secondary_label}
                                </Link>
                            </Button>
                        </div>
                    )}
                </HeroSlider>

                <main className="mx-auto flex w-full max-w-6xl flex-col gap-16 p-4 py-12">
                    <section className="flex flex-col gap-8">
                        <div className="mx-auto max-w-2xl text-center">
                            <h2 className="text-2xl font-semibold">
                                About GoldWave
                            </h2>
                            <p className="text-muted-foreground mt-2">
                                GoldWave brings members together around real
                                gold and silver jewellery ownership — structured
                                plans, transparent monthly draws, and rewards
                                that grow with your network.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                            {FEATURES.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="flex flex-col items-center gap-3 rounded-xl border p-6 text-center"
                                >
                                    <div className="flex size-12 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                                        <feature.icon className="size-6" />
                                    </div>
                                    <h3 className="font-medium">
                                        {feature.title}
                                    </h3>
                                    <p className="text-muted-foreground text-sm">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="border-t p-6 text-center">
                    <p className="text-muted-foreground text-sm">
                        © {new Date().getFullYear()} GoldWave. All rights
                        reserved.
                    </p>
                </footer>
            </div>
        </>
    );
}
