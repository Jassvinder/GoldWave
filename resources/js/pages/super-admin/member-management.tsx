import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/utils';
import { exportMethod, show } from '@/routes/super-admin/members';

type MemberRow = {
    id: number;
    customer_id: string;
    name: string | null;
    mobile: string | null;
    email: string | null;
    plan: string | null;
    sponsor_customer_id: string | null;
    status: string;
    activated_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    members: Paginated<MemberRow>;
    filters: { search?: string; plan?: string; status?: string };
};

/** INSTRUCTIONS.md's Admin Member Management — company-wide member list with search/filter/pagination/export. */
export default function SuperAdminMemberManagement({
    members,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get('/super-admin/members', { ...filters, search }, { preserveState: true });
    };

    const goToPage = (page: number) => {
        router.get('/super-admin/members', { ...filters, search, page }, { preserveState: true });
    };

    return (
        <>
            <Head title="Member Management" />

            <div className="mx-auto flex max-w-4xl flex-col gap-6 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-2xl">Members</CardTitle>
                        <Button variant="outline" size="sm" asChild>
                            <a href={exportMethod.url()}>Export CSV</a>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <form
                            onSubmit={submitSearch}
                            className="flex items-center gap-2"
                        >
                            <Input
                                placeholder="Search by Customer ID, name, or mobile"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>

                        <div className="flex flex-col gap-2">
                            {members.data.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No members found.
                                </p>
                            )}
                            {members.data.map((member) => (
                                <Link
                                    key={member.id}
                                    href={show.url(member.id)}
                                    className="flex items-start justify-between gap-3 rounded-md border p-3 text-sm"
                                >
                                    <div className="min-w-0">
                                        <div className="font-medium">
                                            {member.customer_id} —{' '}
                                            {member.name ?? '—'}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {member.mobile ?? '—'} · Plan:{' '}
                                            {member.plan ?? '—'} · Sponsor:{' '}
                                            {member.sponsor_customer_id ??
                                                '—'}
                                        </div>
                                    </div>
                                    <div className="shrink-0 text-right whitespace-nowrap">
                                        <div className="font-medium capitalize">
                                            {member.status}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {formatDate(member.activated_at)}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>

                        {members.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={members.current_page <= 1}
                                    onClick={() =>
                                        goToPage(members.current_page - 1)
                                    }
                                >
                                    Previous
                                </Button>
                                <span className="text-muted-foreground text-sm">
                                    Page {members.current_page} of{' '}
                                    {members.last_page} ({members.total}{' '}
                                    members)
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        members.current_page >=
                                        members.last_page
                                    }
                                    onClick={() =>
                                        goToPage(members.current_page + 1)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
