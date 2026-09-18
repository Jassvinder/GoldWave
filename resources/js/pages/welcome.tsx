import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, Gem, Sparkles, TrendingUp } from 'lucide-react';
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

/** T-104 — public landing page at `/`, replacing the untouched Laravel starter-kit `welcome.tsx`. */
export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="GoldWave — Gold & Silver Jewellery Membership" />

            <div className="bg-background text-foreground min-h-screen">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between p-4">
                    <div className="flex items-center gap-2">
                        <img
                            src="/Images/Logo.png"
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

                <main className="mx-auto flex w-full max-w-6xl flex-col gap-16 p-4 py-12">
                    <section className="grid grid-cols-1 items-center gap-10 lg:grid-cols-2">
                        <div className="flex flex-col gap-6">
                            <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
                                Own real gold & silver jewellery, one easy
                                instalment at a time.
                            </h1>
                            <p className="text-muted-foreground text-lg">
                                GoldWave is a jewellery membership program —
                                pick a plan, pay in convenient monthly
                                instalments, and receive genuine gold or silver
                                jewellery, while your own network builds rewards
                                alongside you.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                {!auth.user && (
                                    <>
                                        <Button size="lg" asChild>
                                            <Link href={registerShow()}>
                                                Join Now
                                            </Link>
                                        </Button>
                                        <Button
                                            size="lg"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={memberLogin()}>
                                                Member Login
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Temporary placeholder panel standing in for real jewellery photography, pending the user's real photos (T-104). */}
                        <div className="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-amber-400 via-amber-500 to-yellow-600 shadow-lg dark:from-amber-600 dark:via-amber-700 dark:to-yellow-800">
                            <Sparkles className="size-32 text-white/90" />
                        </div>
                    </section>

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
