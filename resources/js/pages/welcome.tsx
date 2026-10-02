import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    CalendarClock,
    ChevronDown,
    Coins,
    Crown,
    Gem,
    Gift,
    HandCoins,
    Mail,
    MapPin,
    MessageCircle,
    Network,
    Phone,
    Rocket,
    ShieldCheck,
    Sparkles,
    Store as StoreIcon,
    UserPlus,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, login as staffLogin } from '@/routes';
import { login as memberLogin } from '@/routes/member';
import { show as registerShow } from '@/routes/registration';

type Hero = {
    headline: string;
    subtext: string;
    cta_primary_label: string;
    cta_secondary_label: string;
};

type Contact = {
    phone: string;
    whatsapp: string;
    email: string;
    address: string;
};

type Plan = {
    id: number;
    name: string;
    amount: string;
    installment_count: number | null;
    product_category: 'gold' | 'silver';
    fixed_weight_grams: string | null;
};

type PublicStore = { id: number; name: string; location: string | null };

type Props = {
    hero: Hero;
    contact: Contact;
    plans: Plan[];
    stores: PublicStore[];
};

const NAV = [
    { href: '#plans', label: 'Plans' },
    { href: '#how-it-works', label: 'How it works' },
    { href: '#rewards', label: 'Rewards' },
    { href: '#stores', label: 'Stores' },
    { href: '#contact', label: 'Contact' },
];

const WHY = [
    {
        icon: Gem,
        title: 'Real jewellery',
        description:
            'Every plan ends in genuine gold or silver jewellery — not just points on a screen.',
        tile: 'from-amber-400 to-orange-500',
        card: 'bg-amber-50 border-amber-200',
    },
    {
        icon: Wallet,
        title: 'Easy instalments',
        description:
            'Pay monthly in small amounts, or pay once with a Direct plan. Cash or GPay / UPI.',
        tile: 'from-violet-500 to-fuchsia-500',
        card: 'bg-violet-50 border-violet-200',
    },
    {
        icon: CalendarClock,
        title: 'Monthly lucky draw',
        description:
            'Eligible members take part in a transparent draw every month, with real winners.',
        tile: 'from-rose-500 to-pink-500',
        card: 'bg-rose-50 border-rose-200',
    },
    {
        icon: Network,
        title: 'Team rewards',
        description:
            'Introduce friends and family, and earn rewards as your team grows with you.',
        tile: 'from-teal-400 to-emerald-500',
        card: 'bg-teal-50 border-teal-200',
    },
];

const STEPS = [
    {
        icon: UserPlus,
        title: 'Join with a sponsor code',
        description:
            'Get the code from the member who introduced you and register in a few minutes.',
        tile: 'from-sky-400 to-blue-600',
    },
    {
        icon: Coins,
        title: 'Choose your plan',
        description:
            'Pick a Silver or Gold plan — monthly instalments or a one-time payment.',
        tile: 'from-violet-500 to-purple-600',
    },
    {
        icon: HandCoins,
        title: 'Pay with ease',
        description:
            'Pay in cash or by GPay / UPI. Every payment is checked and approved by our team.',
        tile: 'from-rose-500 to-pink-600',
    },
    {
        icon: Gift,
        title: 'Get your jewellery',
        description:
            'Collect your gold or silver jewellery from a GoldWave store once your plan completes.',
        tile: 'from-amber-400 to-orange-600',
    },
];

const REWARDS = [
    {
        icon: Network,
        title: 'Level Income',
        description: "A share from your team's plan payments, level by level.",
        tile: 'from-sky-400 to-indigo-500',
    },
    {
        icon: Crown,
        title: 'Pair Rewards',
        description: 'Milestone rewards as your left and right teams grow.',
        tile: 'from-amber-400 to-orange-500',
    },
    {
        icon: Sparkles,
        title: 'Monthly Draw',
        description: 'A real winner from every eligible group, every month.',
        tile: 'from-fuchsia-500 to-pink-500',
    },
    {
        icon: Rocket,
        title: 'Income Booster',
        description: 'Extra rewards for members who build an active team.',
        tile: 'from-emerald-400 to-teal-500',
    },
    {
        icon: StoreIcon,
        title: 'Purchase Income',
        description:
            'Earn when members of your team buy jewellery at our stores.',
        tile: 'from-rose-400 to-red-500',
    },
];

const FAQS = [
    {
        question: 'What is GoldWave?',
        answer: 'GoldWave is a gold and silver jewellery membership program. You choose a plan, pay for it in easy instalments (or at once), and receive real jewellery — while also earning rewards when your team grows.',
    },
    {
        question: 'Will I get real jewellery or metal?',
        answer: 'Every plan gives you genuine gold or silver jewellery items (not coins or bars). EMI plans give a fixed weight; Direct plans let you choose jewellery at the current rate.',
    },
    {
        question: 'How do I join?',
        answer: 'You need a sponsor code from an existing GoldWave member. Open "Join Now", enter the code, fill in your details, choose a plan and make the first payment.',
    },
    {
        question: 'How can I pay?',
        answer: 'You can pay in cash at a store or by GPay / UPI (with the transaction reference and a screenshot). Our team approves every payment and you can track it in your member portal.',
    },
    {
        question: 'Is any income guaranteed?',
        answer: 'No. Rewards depend on the plan rules and on real activity in your team. Your jewellery entitlement is what your plan promises; rewards are an extra on top.',
    },
];

/** Indian-grouped rupee amount without decimals, e.g. "₹10,000". */
function rupees(value: string | number): string {
    return `₹${Number(value).toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
}

function entitlement(plan: Plan): string {
    const metal = plan.product_category === 'gold' ? 'Gold' : 'Silver';

    if (plan.fixed_weight_grams) {
        return `${Number(plan.fixed_weight_grams)} gm ${metal} Jewellery`;
    }

    return `${metal} jewellery of your choice at the current rate`;
}

/** T-104 — public landing page at `/`. Hero copy editable since T-115; colourful redesign (no photos yet) with plans, stores, FAQ and Super-Admin-editable contact details on 02-10-2026. */
export default function Welcome({ hero, contact, plans, stores }: Props) {
    const { auth } = usePage().props;
    const goldPlan = plans.find(
        (plan) => plan.product_category === 'gold' && plan.installment_count,
    );
    const silverPlan = plans.find(
        (plan) => plan.product_category === 'silver' && plan.installment_count,
    );

    return (
        <>
            <Head title="GoldWave — Gold & Silver Jewellery Membership" />

            <div className="min-h-screen bg-white text-slate-800">
                {/* Header */}
                <header className="sticky top-0 z-30 border-b border-white/40 bg-white/85 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3">
                        <a href="#top" className="flex items-center gap-2">
                            <img
                                src="/Images/Logo.webp"
                                alt="GoldWave"
                                className="size-10 rounded-full object-cover ring-2 ring-amber-300"
                            />
                            <span className="font-display bg-linear-to-r from-fuchsia-600 via-rose-500 to-amber-500 bg-clip-text text-xl font-bold text-transparent">
                                GoldWave
                            </span>
                        </a>

                        <nav className="hidden items-center gap-6 text-sm font-medium text-slate-600 lg:flex">
                            {NAV.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className="transition-colors hover:text-fuchsia-600"
                                >
                                    {item.label}
                                </a>
                            ))}
                        </nav>

                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    className="hidden border-fuchsia-200 text-fuchsia-700 hover:bg-fuchsia-50 hover:text-fuchsia-800 sm:inline-flex"
                                    asChild
                                >
                                    <Link href={memberLogin()}>
                                        Member Login
                                    </Link>
                                </Button>
                                <Button
                                    className="bg-linear-to-r from-fuchsia-600 to-rose-500 text-white shadow-md shadow-rose-500/30 hover:opacity-90"
                                    asChild
                                >
                                    <Link href={registerShow()}>Join Now</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </header>

                {/* Hero */}
                <section
                    id="top"
                    className="relative isolate overflow-hidden bg-linear-to-br from-violet-700 via-fuchsia-600 to-orange-400"
                >
                    <div
                        aria-hidden
                        className="absolute -top-24 -left-24 -z-10 size-96 rounded-full bg-yellow-300/40 blur-3xl"
                    />
                    <div
                        aria-hidden
                        className="absolute -right-20 -bottom-32 -z-10 size-[28rem] rounded-full bg-sky-400/40 blur-3xl"
                    />

                    <div className="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pt-16 pb-28 lg:grid-cols-2 lg:pt-24 lg:pb-36">
                        <div className="flex flex-col gap-6 text-white">
                            <span className="inline-flex w-fit items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-medium ring-1 ring-white/30">
                                <Sparkles className="size-4 text-yellow-300" />
                                Gold & Silver Jewellery Membership
                            </span>
                            <h1 className="font-display text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                                {hero.headline}
                            </h1>
                            <p className="max-w-xl text-lg text-white/90">
                                {hero.subtext}
                            </p>
                            {!auth.user && (
                                <div className="flex flex-wrap gap-3">
                                    <Button
                                        size="lg"
                                        className="bg-yellow-300 font-semibold text-violet-900 shadow-lg shadow-black/20 hover:bg-yellow-200"
                                        asChild
                                    >
                                        <Link href={registerShow()}>
                                            {hero.cta_primary_label}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        className="border-white/70 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                        asChild
                                    >
                                        <Link href={memberLogin()}>
                                            {hero.cta_secondary_label}
                                        </Link>
                                    </Button>
                                </div>
                            )}
                            <ul className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/90">
                                {[
                                    'Genuine jewellery',
                                    'Easy EMIs',
                                    'Cash or GPay / UPI',
                                ].map((item) => (
                                    <li
                                        key={item}
                                        className="flex items-center gap-1.5"
                                    >
                                        <BadgeCheck className="size-4 text-yellow-300" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <HeroVisual
                            goldPlan={goldPlan}
                            silverPlan={silverPlan}
                        />
                    </div>

                    {/* The "wave" in GoldWave */}
                    <svg
                        aria-hidden
                        viewBox="0 0 1440 120"
                        preserveAspectRatio="none"
                        className="absolute bottom-0 left-0 h-16 w-full text-white sm:h-24"
                    >
                        <path
                            fill="currentColor"
                            d="M0,64 C240,128 480,0 720,48 C960,96 1200,112 1440,40 L1440,120 L0,120 Z"
                        />
                    </svg>
                </section>

                {/* Why GoldWave */}
                <section className="mx-auto w-full max-w-6xl px-4 py-16">
                    <SectionHeading
                        eyebrow="Why GoldWave"
                        title="Jewellery you own, rewards you earn"
                        description="A simple plan, honest payments, and real gold & silver at the end of it."
                    />
                    <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {WHY.map((item) => (
                            <div
                                key={item.title}
                                className={cn(
                                    'flex flex-col gap-3 rounded-2xl border p-6 transition-transform hover:-translate-y-1',
                                    item.card,
                                )}
                            >
                                <IconTile icon={item.icon} tile={item.tile} />
                                <h3 className="font-display text-lg font-semibold text-slate-900">
                                    {item.title}
                                </h3>
                                <p className="text-sm text-slate-600">
                                    {item.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Plans */}
                <section
                    id="plans"
                    className="scroll-mt-20 bg-linear-to-b from-amber-50 via-rose-50 to-violet-50 py-16"
                >
                    <div className="mx-auto w-full max-w-6xl px-4">
                        <SectionHeading
                            eyebrow="Our Plans"
                            title="Pick the plan that suits you"
                            description="Monthly EMI plans with a fixed jewellery weight, or one-time Direct plans."
                        />
                        <div className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {plans.map((plan) => (
                                <PlanCard
                                    key={plan.id}
                                    plan={plan}
                                    showJoin={!auth.user}
                                />
                            ))}
                        </div>
                    </div>
                </section>

                {/* How it works */}
                <section
                    id="how-it-works"
                    className="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-16"
                >
                    <SectionHeading
                        eyebrow="How it works"
                        title="Four simple steps"
                        description="From joining to wearing your jewellery."
                    />
                    <ol className="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        {STEPS.map((step, index) => (
                            <li
                                key={step.title}
                                className="relative flex flex-col items-center gap-3 text-center"
                            >
                                {index < STEPS.length - 1 && (
                                    <div
                                        aria-hidden
                                        className="absolute top-8 left-[calc(50%+2.5rem)] hidden h-0.5 w-[calc(100%-5rem)] bg-linear-to-r from-slate-300 to-slate-200 lg:block"
                                    />
                                )}
                                <div
                                    className={cn(
                                        'relative flex size-16 items-center justify-center rounded-2xl bg-linear-to-br text-white shadow-lg',
                                        step.tile,
                                    )}
                                >
                                    <step.icon className="size-7" />
                                    <span className="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                                        {index + 1}
                                    </span>
                                </div>
                                <h3 className="font-display text-lg font-semibold text-slate-900">
                                    {step.title}
                                </h3>
                                <p className="text-sm text-slate-600">
                                    {step.description}
                                </p>
                            </li>
                        ))}
                    </ol>
                </section>

                {/* Rewards */}
                <section
                    id="rewards"
                    className="relative isolate scroll-mt-20 overflow-hidden bg-indigo-950 py-16 text-white"
                >
                    <div
                        aria-hidden
                        className="absolute top-0 left-1/3 -z-10 size-96 rounded-full bg-fuchsia-600/30 blur-3xl"
                    />
                    <div className="mx-auto w-full max-w-6xl px-4">
                        <SectionHeading
                            eyebrow="Member Rewards"
                            title="Grow together, earn together"
                            description="Besides your jewellery, GoldWave rewards you for building an active team."
                            dark
                        />
                        <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
                            {REWARDS.map((reward) => (
                                <div
                                    key={reward.title}
                                    className="flex flex-col gap-3 rounded-2xl bg-white/5 p-5 ring-1 ring-white/10 transition-colors hover:bg-white/10"
                                >
                                    <IconTile
                                        icon={reward.icon}
                                        tile={reward.tile}
                                    />
                                    <h3 className="font-display font-semibold">
                                        {reward.title}
                                    </h3>
                                    <p className="text-sm text-indigo-100/80">
                                        {reward.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <p className="mt-8 flex items-start justify-center gap-2 text-center text-xs text-indigo-200/80">
                            <ShieldCheck className="size-4 shrink-0" />
                            Rewards depend on the plan rules and real team
                            activity. No income is guaranteed.
                        </p>
                    </div>
                </section>

                {/* Stores */}
                {stores.length > 0 && (
                    <section
                        id="stores"
                        className="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-16"
                    >
                        <SectionHeading
                            eyebrow="Our Stores"
                            title="Visit a GoldWave store"
                            description="Pay in cash, collect your jewellery, or shop more at a store near you."
                        />
                        <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {stores.map((store, index) => (
                                <div
                                    key={store.id}
                                    className="flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <IconTile
                                        icon={StoreIcon}
                                        tile={
                                            STORE_TILES[
                                                index % STORE_TILES.length
                                            ]
                                        }
                                    />
                                    <div className="flex flex-col gap-1">
                                        <h3 className="font-display font-semibold text-slate-900">
                                            {store.name}
                                        </h3>
                                        {store.location && (
                                            <p className="flex items-start gap-1 text-sm text-slate-600">
                                                <MapPin className="mt-0.5 size-3.5 shrink-0" />
                                                {store.location}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* FAQ */}
                <section className="bg-slate-50 py-16">
                    <div className="mx-auto w-full max-w-3xl px-4">
                        <SectionHeading
                            eyebrow="FAQ"
                            title="Questions, answered"
                        />
                        <div className="mt-10 flex flex-col gap-3">
                            {FAQS.map((faq) => (
                                <details
                                    key={faq.question}
                                    className="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm open:ring-2 open:ring-fuchsia-200"
                                >
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-slate-900 [&::-webkit-details-marker]:hidden">
                                        {faq.question}
                                        <ChevronDown className="size-5 shrink-0 text-fuchsia-600 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <p className="mt-3 text-sm text-slate-600">
                                        {faq.answer}
                                    </p>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Contact + CTA */}
                <section
                    id="contact"
                    className="mx-auto w-full max-w-6xl scroll-mt-20 px-4 py-16"
                >
                    <div className="relative isolate overflow-hidden rounded-3xl bg-linear-to-r from-fuchsia-600 via-rose-500 to-orange-400 p-8 text-white shadow-xl sm:p-12">
                        <div
                            aria-hidden
                            className="absolute -top-16 -right-16 -z-10 size-64 rounded-full bg-yellow-300/40 blur-3xl"
                        />
                        <div className="grid gap-10 lg:grid-cols-2">
                            <div className="flex flex-col gap-4">
                                <h2 className="font-display text-3xl font-bold sm:text-4xl">
                                    Start your jewellery journey today
                                </h2>
                                <p className="text-white/90">
                                    Have a question before you join? Call us,
                                    message us on WhatsApp, or visit a store —
                                    we are happy to help.
                                </p>
                                {!auth.user && (
                                    <Button
                                        size="lg"
                                        className="w-fit bg-white font-semibold text-fuchsia-700 hover:bg-yellow-100"
                                        asChild
                                    >
                                        <Link href={registerShow()}>
                                            {hero.cta_primary_label}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                )}
                            </div>
                            <ContactList contact={contact} />
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-slate-950 text-slate-300">
                    <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="flex flex-col gap-3 lg:col-span-2">
                            <div className="flex items-center gap-2">
                                <img
                                    src="/Images/Logo.webp"
                                    alt=""
                                    className="size-9 rounded-full object-cover"
                                />
                                <span className="font-display text-lg font-bold text-white">
                                    GoldWave
                                </span>
                            </div>
                            <p className="max-w-sm text-sm text-slate-400">
                                Gold & silver jewellery membership — easy
                                instalments, real jewellery, and rewards that
                                grow with your team.
                            </p>
                        </div>
                        <FooterLinks
                            title="Explore"
                            links={NAV.filter(
                                (item) =>
                                    item.href !== '#stores' ||
                                    stores.length > 0,
                            ).map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className="hover:text-white"
                                >
                                    {item.label}
                                </a>
                            ))}
                        />
                        <FooterLinks
                            title="Account"
                            links={[
                                <Link
                                    key="join"
                                    href={registerShow()}
                                    className="hover:text-white"
                                >
                                    Join Now
                                </Link>,
                                <Link
                                    key="member"
                                    href={memberLogin()}
                                    className="hover:text-white"
                                >
                                    Member Login
                                </Link>,
                                <Link
                                    key="staff"
                                    href={staffLogin()}
                                    className="hover:text-white"
                                >
                                    Staff / Store Login
                                </Link>,
                            ]}
                        />
                    </div>
                    <div className="border-t border-white/10 px-4 py-5 text-center text-sm text-slate-500">
                        © {new Date().getFullYear()} GoldWave. All rights
                        reserved.
                    </div>
                </footer>
            </div>
        </>
    );
}

const STORE_TILES = [
    'from-amber-400 to-orange-500',
    'from-violet-500 to-fuchsia-500',
    'from-teal-400 to-emerald-500',
    'from-sky-400 to-blue-600',
    'from-rose-500 to-pink-500',
];

function IconTile({ icon: Icon, tile }: { icon: LucideIcon; tile: string }) {
    return (
        <div
            className={cn(
                'flex size-12 shrink-0 items-center justify-center rounded-xl bg-linear-to-br text-white shadow-md',
                tile,
            )}
        >
            <Icon className="size-6" />
        </div>
    );
}

function SectionHeading({
    eyebrow,
    title,
    description,
    dark = false,
}: {
    eyebrow: string;
    title: string;
    description?: string;
    dark?: boolean;
}) {
    return (
        <div className="mx-auto flex max-w-2xl flex-col items-center gap-3 text-center">
            <span
                className={cn(
                    'rounded-full px-3 py-1 text-xs font-semibold tracking-wider uppercase',
                    dark
                        ? 'bg-white/10 text-yellow-300'
                        : 'bg-fuchsia-100 text-fuchsia-700',
                )}
            >
                {eyebrow}
            </span>
            <h2
                className={cn(
                    'font-display text-3xl font-bold tracking-tight sm:text-4xl',
                    dark ? 'text-white' : 'text-slate-900',
                )}
            >
                {title}
            </h2>
            {description && (
                <p className={dark ? 'text-indigo-100/80' : 'text-slate-600'}>
                    {description}
                </p>
            )}
        </div>
    );
}

function PlanCard({ plan, showJoin }: { plan: Plan; showJoin: boolean }) {
    const gold = plan.product_category === 'gold';
    const isEmi = plan.installment_count !== null;

    return (
        <div className="flex flex-col overflow-hidden rounded-2xl bg-white shadow-lg ring-1 shadow-slate-200/70 ring-slate-100 transition-transform hover:-translate-y-1">
            <div
                className={cn(
                    'flex items-center justify-between bg-linear-to-r px-6 py-5 text-white',
                    gold
                        ? 'from-amber-400 via-orange-500 to-rose-500'
                        : 'from-sky-400 via-indigo-500 to-violet-500',
                )}
            >
                <div>
                    <p className="text-xs font-semibold tracking-wider text-white/80 uppercase">
                        {gold ? 'Gold' : 'Silver'} ·{' '}
                        {isEmi ? 'EMI plan' : 'One-time'}
                    </p>
                    <h3 className="font-display text-2xl font-bold">
                        {plan.name}
                    </h3>
                </div>
                {gold ? (
                    <Crown className="size-9 text-white/90" />
                ) : (
                    <Gem className="size-9 text-white/90" />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-4 p-6">
                <p className="flex items-baseline gap-1">
                    <span className="font-display text-3xl font-extrabold text-slate-900">
                        {rupees(plan.amount)}
                    </span>
                    <span className="text-sm text-slate-500">
                        {isEmi
                            ? `/ month × ${plan.installment_count}`
                            : 'one-time'}
                    </span>
                </p>
                {isEmi && (
                    <p className="text-sm text-slate-500">
                        Total{' '}
                        {rupees(
                            Number(plan.amount) * (plan.installment_count ?? 0),
                        )}
                    </p>
                )}
                <p
                    className={cn(
                        'flex items-start gap-2 rounded-lg px-3 py-2 text-sm font-medium',
                        gold
                            ? 'bg-amber-50 text-amber-800'
                            : 'bg-indigo-50 text-indigo-800',
                    )}
                >
                    <Gift className="mt-0.5 size-4 shrink-0" />
                    {entitlement(plan)}
                </p>
                {showJoin && (
                    <Button
                        variant="outline"
                        className={cn(
                            'mt-auto',
                            gold
                                ? 'border-orange-200 text-orange-700 hover:bg-orange-50 hover:text-orange-800'
                                : 'border-indigo-200 text-indigo-700 hover:bg-indigo-50 hover:text-indigo-800',
                        )}
                        asChild
                    >
                        <Link href={registerShow()}>Join with {plan.name}</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}

/** Decorative stack of plan "cards" on the hero — stands in for jewellery photos until the client supplies them. */
function HeroVisual({
    goldPlan,
    silverPlan,
}: {
    goldPlan?: Plan;
    silverPlan?: Plan;
}) {
    return (
        <div aria-hidden className="relative mx-auto h-80 w-full max-w-md">
            {silverPlan && (
                <div className="absolute top-4 left-0 w-60 -rotate-6 rounded-2xl bg-linear-to-br from-sky-300 via-indigo-400 to-violet-500 p-5 text-white shadow-2xl ring-1 ring-white/40">
                    <Gem className="size-8" />
                    <p className="mt-6 text-xs tracking-wider text-white/80 uppercase">
                        Silver plan
                    </p>
                    <p className="font-display text-xl font-bold">
                        {silverPlan.name}
                    </p>
                    <p className="text-sm text-white/90">
                        {rupees(silverPlan.amount)} ×{' '}
                        {silverPlan.installment_count}
                    </p>
                </div>
            )}
            {goldPlan && (
                <div className="absolute top-16 right-0 w-64 rotate-6 rounded-2xl bg-linear-to-br from-yellow-300 via-amber-400 to-orange-500 p-5 text-amber-950 shadow-2xl ring-1 ring-white/50">
                    <Crown className="size-8" />
                    <p className="mt-6 text-xs tracking-wider text-amber-900/80 uppercase">
                        Gold plan
                    </p>
                    <p className="font-display text-xl font-bold">
                        {goldPlan.name}
                    </p>
                    <p className="text-sm">
                        {rupees(goldPlan.amount)} × {goldPlan.installment_count}{' '}
                        → {entitlement(goldPlan)}
                    </p>
                </div>
            )}
            <div className="absolute bottom-0 left-10 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-2xl">
                <div className="flex size-11 items-center justify-center rounded-xl bg-linear-to-br from-rose-500 to-fuchsia-600 text-white">
                    <CalendarClock className="size-5" />
                </div>
                <div>
                    <p className="font-display text-sm font-bold text-slate-900">
                        Monthly Lucky Draw
                    </p>
                    <p className="text-xs text-slate-500">
                        A real winner every month
                    </p>
                </div>
            </div>
        </div>
    );
}

function ContactList({ contact }: { contact: Contact }) {
    const whatsappDigits = contact.whatsapp.replace(/\D/g, '');
    const items = [
        contact.phone && {
            icon: Phone,
            label: 'Call us',
            value: contact.phone,
            href: `tel:${contact.phone.replace(/\s/g, '')}`,
        },
        whatsappDigits && {
            icon: MessageCircle,
            label: 'WhatsApp',
            value: contact.whatsapp,
            href: `https://wa.me/${whatsappDigits}`,
        },
        contact.email && {
            icon: Mail,
            label: 'Email',
            value: contact.email,
            href: `mailto:${contact.email}`,
        },
        contact.address && {
            icon: MapPin,
            label: 'Head office',
            value: contact.address,
            href: null,
        },
    ].filter(Boolean) as {
        icon: LucideIcon;
        label: string;
        value: string;
        href: string | null;
    }[];

    return (
        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {items.map((item) => {
                const body = (
                    <>
                        <item.icon className="size-5 shrink-0 text-yellow-200" />
                        <span className="flex min-w-0 flex-col">
                            <span className="text-xs text-white/75">
                                {item.label}
                            </span>
                            <span className="font-medium break-words">
                                {item.value}
                            </span>
                        </span>
                    </>
                );
                const className =
                    'flex h-full items-start gap-3 rounded-xl bg-white/15 p-4 ring-1 ring-white/25';

                return (
                    <li key={item.label}>
                        {item.href ? (
                            <a
                                href={item.href}
                                target={
                                    item.href.startsWith('http')
                                        ? '_blank'
                                        : undefined
                                }
                                rel="noreferrer"
                                className={cn(
                                    className,
                                    'transition-colors hover:bg-white/25',
                                )}
                            >
                                {body}
                            </a>
                        ) : (
                            <div className={className}>{body}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function FooterLinks({ title, links }: { title: string; links: ReactNode[] }) {
    return (
        <div className="flex flex-col gap-3">
            <h3 className="font-display text-sm font-semibold tracking-wider text-white uppercase">
                {title}
            </h3>
            <ul className="flex flex-col gap-2 text-sm">
                {links.map((link, index) => (
                    <li key={index}>{link}</li>
                ))}
            </ul>
        </div>
    );
}
