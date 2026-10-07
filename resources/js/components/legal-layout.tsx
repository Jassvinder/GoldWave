import { Head, Link } from '@inertiajs/react';
import { Mail, MapPin, MessageCircle, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { privacy, refund, terms } from '@/routes/legal';

export type LegalContact = {
    phone: string;
    whatsapp: string;
    email: string;
    address: string;
};

export type LegalPageProps = {
    contact: LegalContact;
    last_updated: string;
};

const PAGES = [
    { href: terms(), label: 'Terms & Conditions', key: 'terms' },
    { href: privacy(), label: 'Privacy Policy', key: 'privacy' },
    { href: refund(), label: 'Refund & Cancellation', key: 'refund' },
] as const;

type Props = LegalPageProps & {
    page: (typeof PAGES)[number]['key'];
    title: string;
    intro: string;
    children: ReactNode;
};

/** T-207 (02-10-2026) — shared frame for the public legal pages, in the home page's colours. */
export function LegalLayout({
    page,
    title,
    intro,
    contact,
    last_updated,
    children,
}: Props) {
    return (
        <>
            <Head title={`${title} — GoldWave`} />

            <div className="min-h-screen bg-slate-50 text-slate-800">
                <header className="border-b bg-white">
                    <div className="mx-auto flex w-full max-w-4xl items-center justify-between gap-4 px-4 py-3">
                        <Link href={home()} className="flex items-center gap-2">
                            <img
                                src="/Images/Logo.webp"
                                alt="GoldWave"
                                className="size-9 rounded-full object-cover ring-2 ring-amber-300"
                            />
                            <span className="font-display bg-linear-to-r from-fuchsia-600 via-rose-500 to-amber-500 bg-clip-text text-lg font-bold text-transparent">
                                GoldWave
                            </span>
                        </Link>
                        <Link
                            href={home()}
                            className="text-sm font-medium text-slate-600 hover:text-fuchsia-600"
                        >
                            Back to home
                        </Link>
                    </div>
                </header>

                <section className="bg-linear-to-br from-violet-700 via-fuchsia-600 to-orange-400 text-white">
                    <div className="mx-auto w-full max-w-4xl px-4 py-10">
                        <h1 className="font-display text-3xl font-bold sm:text-4xl">
                            {title}
                        </h1>
                        <p className="mt-2 max-w-2xl text-white/90">{intro}</p>
                        <p className="mt-4 text-sm text-white/80">
                            Last updated: {last_updated}
                        </p>
                    </div>
                </section>

                <main className="mx-auto w-full max-w-4xl px-4 py-8">
                    <nav
                        aria-label="Legal pages"
                        className="mb-6 flex flex-wrap gap-2"
                    >
                        {PAGES.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={cn(
                                    'rounded-full px-3 py-1 text-sm font-medium ring-1',
                                    item.key === page
                                        ? 'bg-fuchsia-600 text-white ring-fuchsia-600'
                                        : 'bg-white text-slate-600 ring-slate-200 hover:text-fuchsia-700',
                                )}
                                aria-current={
                                    item.key === page ? 'page' : undefined
                                }
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <article className="flex flex-col gap-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-10">
                        {children}

                        <LegalSection title="Contact us">
                            <ContactDetails contact={contact} />
                        </LegalSection>
                    </article>
                </main>

                <footer className="border-t bg-white px-4 py-5 text-center text-sm text-slate-500">
                    © {new Date().getFullYear()} GoldWave. All rights reserved.
                </footer>
            </div>
        </>
    );
}

/** One numbered or titled block of a legal page. */
export function LegalSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3">
            <h2 className="font-display text-xl font-semibold text-slate-900">
                {title}
            </h2>
            <div className="flex flex-col gap-3 text-[15px] leading-relaxed text-slate-700 [&_li]:ml-5 [&_li]:list-disc [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-1.5">
                {children}
            </div>
        </section>
    );
}

function ContactDetails({ contact }: { contact: LegalContact }) {
    const items = [
        contact.phone && {
            icon: Phone,
            value: contact.phone,
            href: `tel:${contact.phone.replace(/\s/g, '')}`,
        },
        contact.whatsapp && {
            icon: MessageCircle,
            value: `WhatsApp ${contact.whatsapp}`,
            href: `https://wa.me/${contact.whatsapp.replace(/\D/g, '')}`,
        },
        contact.email && {
            icon: Mail,
            value: contact.email,
            href: `mailto:${contact.email}`,
        },
        contact.address && {
            icon: MapPin,
            value: contact.address,
            href: null,
        },
    ].filter(Boolean) as {
        icon: typeof Phone;
        value: string;
        href: string | null;
    }[];

    if (items.length === 0) {
        return <p>Please contact your sponsor or visit any GoldWave store.</p>;
    }

    return (
        <div className="flex flex-col gap-2">
            {items.map((item) => (
                <div key={item.value} className="flex items-start gap-2">
                    <item.icon className="mt-0.5 size-4 shrink-0 text-fuchsia-600" />
                    {item.href ? (
                        <a
                            href={item.href}
                            className="text-fuchsia-700 underline-offset-2 hover:underline"
                            target={
                                item.href.startsWith('http')
                                    ? '_blank'
                                    : undefined
                            }
                            rel="noreferrer"
                        >
                            {item.value}
                        </a>
                    ) : (
                        <span>{item.value}</span>
                    )}
                </div>
            ))}
        </div>
    );
}
