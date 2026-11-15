import { Head } from '@inertiajs/react';
import { BookOpen, MapPin, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { iconBadgeColorClasses } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    HELP,
    type HelpPortal,
    type HelpSection,
    type HelpTopic,
} from '@/lib/help-content';
import { cn } from '@/lib/utils';

const normalize = (value: string) => value.toLowerCase().trim();

function topicMatches(topic: HelpTopic, query: string): boolean {
    return [
        topic.title,
        topic.page ?? '',
        ...topic.text,
        ...(topic.steps ?? []),
    ]
        .join(' ')
        .toLowerCase()
        .includes(query);
}

/**
 * T-244 (INSTRUCTIONS.md "Help / Guide") — the in-app Hinglish guide, one per portal (Member, Store, Super Admin /
 * company Admin). A search box filters every topic as you type; the section chips jump to a section. Each topic says
 * where it lives in the sidebar. Super-Admin-only topics are left out for the company Admin.
 */
export function HelpGuide({
    portal,
    isSuperAdmin = false,
}: {
    portal: HelpPortal;
    isSuperAdmin?: boolean;
}) {
    const guide = HELP[portal];
    const [query, setQuery] = useState('');
    const needle = normalize(query);

    const sections: HelpSection[] = useMemo(
        () =>
            guide.sections
                .map((section) => ({
                    ...section,
                    topics: section.topics.filter(
                        (topic) =>
                            (isSuperAdmin || !topic.superAdminOnly) &&
                            (needle === '' || topicMatches(topic, needle)),
                    ),
                }))
                .filter((section) => section.topics.length > 0),
        [guide, isSuperAdmin, needle],
    );

    return (
        <>
            <Head title="Help / Guide" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
                <div className="flex items-start gap-3">
                    <span
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-lg',
                            iconBadgeColorClasses.blue,
                        )}
                    >
                        <BookOpen className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {guide.title}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {guide.intro}
                        </p>
                    </div>
                </div>

                <div className="relative">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        aria-label="Search the guide"
                        placeholder='Search karo — jaise "EMI", "payout", "rate"'
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="pr-10 pl-9"
                    />
                    {query !== '' && (
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            aria-label="Clear search"
                            className="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                            onClick={() => setQuery('')}
                        >
                            <X className="size-4" />
                        </Button>
                    )}
                </div>

                {sections.length > 0 && (
                    <nav
                        aria-label="Guide sections"
                        className="flex flex-wrap gap-2"
                    >
                        {sections.map((section) => (
                            <a
                                key={section.id}
                                href={`#help-${section.id}`}
                                className="hover:bg-accent rounded-full border px-3 py-1 text-sm"
                            >
                                {section.title}
                            </a>
                        ))}
                    </nav>
                )}

                {sections.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center text-sm">
                            &ldquo;{query}&rdquo; ke liye kuch nahi mila. Koi
                            aur shabd try karo, ya search saaf karke saare
                            sections dekho.
                        </CardContent>
                    </Card>
                )}

                {sections.map((section) => {
                    const Icon = section.icon;

                    return (
                        <Card
                            key={section.id}
                            id={`help-${section.id}`}
                            className="scroll-mt-4 gap-3"
                        >
                            <CardHeader className="flex flex-row items-center gap-3">
                                <span
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-lg',
                                        iconBadgeColorClasses.amber,
                                    )}
                                >
                                    <Icon className="size-5" />
                                </span>
                                <CardTitle className="text-lg">
                                    {section.title}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col divide-y">
                                {section.topics.map((topic) => (
                                    <article
                                        key={topic.id}
                                        className="flex flex-col gap-2 py-4 first:pt-0 last:pb-0"
                                    >
                                        <h2 className="font-semibold">
                                            {topic.title}
                                        </h2>
                                        {topic.text.map((paragraph, index) => (
                                            <p
                                                key={index}
                                                className="text-sm leading-relaxed"
                                            >
                                                {paragraph}
                                            </p>
                                        ))}
                                        {topic.steps && (
                                            <ol className="ml-5 list-decimal space-y-1 text-sm">
                                                {topic.steps.map(
                                                    (step, index) => (
                                                        <li key={index}>
                                                            {step}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        )}
                                        {topic.page && (
                                            <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                                <MapPin className="size-3" />
                                                Kahan milega: Sidebar →{' '}
                                                {topic.page}
                                            </p>
                                        )}
                                    </article>
                                ))}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </>
    );
}
