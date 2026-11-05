import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpenText, MapPin, UsersRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import {
    ProgrammeDialog,
    type BranchOption,
    type Programme,
} from './programmes';

type Member = {
    id: number;
    player_id: number;
    player_name: string;
    internal_identifier: string | null;
    player_status: string;
    branch_name: string | null;
    status: string;
    starts_on: string;
    ends_on: string | null;
};
type Paginator = {
    data: Member[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function ProgrammeDetails({
    programme,
    members,
    branch_options,
    selected_branch_id,
    active_tab,
    can_manage,
}: {
    programme: Programme;
    members: Paginator;
    branch_options: BranchOption[];
    selected_branch_id: number | null;
    active_tab: 'details' | 'members';
    can_manage: boolean;
}) {
    const terminology = useAcademyTerminology();
    const branchValue = selected_branch_id?.toString() ?? 'all';
    const tabHref = (tab: 'details' | 'members') =>
        `/app/programmes/${programme.id}?tab=${tab}&branch=${branchValue}`;

    return (
        <>
            <Head title={programme.name} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <Link
                    href="/app/programmes"
                    className="mb-5 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Back to programmes
                </Link>
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {programme.name}
                            </h1>
                            <Badge
                                variant={
                                    programme.is_active
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {programme.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {programme.code ?? 'No programme code'}
                        </p>
                    </div>
                    {can_manage && (
                        <ProgrammeDialog
                            programme={programme}
                            branches={branch_options}
                        />
                    )}
                </div>

                <div
                    role="tablist"
                    aria-label="Programme sections"
                    className="mb-6 flex w-fit gap-1 rounded-lg border bg-muted/40 p-1"
                >
                    <Link
                        href={tabHref('details')}
                        role="tab"
                        aria-selected={active_tab === 'details'}
                        className={`rounded-md px-4 py-2 text-sm font-medium ${active_tab === 'details' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                    >
                        Basic details
                    </Link>
                    <Link
                        href={tabHref('members')}
                        role="tab"
                        aria-selected={active_tab === 'members'}
                        className={`rounded-md px-4 py-2 text-sm font-medium ${active_tab === 'members' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                    >
                        {terminology.plural}{' '}
                        <span className="ml-1 opacity-80">
                            ({members.total})
                        </span>
                    </Link>
                </div>

                {active_tab === 'details' && (
                    <section
                        role="tabpanel"
                        className="grid gap-4 lg:grid-cols-3"
                    >
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BookOpenText className="size-5 text-primary" />
                                    Programme information
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                                            Name
                                        </dt>
                                        <dd className="mt-1 font-medium">
                                            {programme.name}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                                            Code
                                        </dt>
                                        <dd className="mt-1">
                                            {programme.code ?? 'Not set'}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                                            Description
                                        </dt>
                                        <dd className="mt-1 text-sm whitespace-pre-wrap">
                                            {programme.description ??
                                                'No description has been added.'}
                                        </dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPin className="size-5 text-primary" />
                                    Available branches
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {programme.branches.length ? (
                                        programme.branches.map((branch) => (
                                            <Badge
                                                key={branch.id}
                                                variant="outline"
                                            >
                                                {branch.name}
                                                {!branch.is_active &&
                                                    ' · Inactive'}
                                            </Badge>
                                        ))
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Not assigned to a branch.
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </section>
                )}

                {active_tab === 'members' && (
                    <section role="tabpanel" className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <Form
                                action={`/app/programmes/${programme.id}`}
                                method="get"
                                className="flex flex-wrap items-end gap-2"
                            >
                                {() => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="tab"
                                            value="members"
                                        />
                                        <div className="grid gap-1">
                                            <label
                                                htmlFor="programme-branch-filter"
                                                className="text-sm font-medium"
                                            >
                                                Branch
                                            </label>
                                            <select
                                                id="programme-branch-filter"
                                                name="branch"
                                                defaultValue={branchValue}
                                                className="h-9 min-w-56 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="all">
                                                    All branches
                                                </option>
                                                {branch_options.map(
                                                    (branch) => (
                                                        <option
                                                            key={branch.id}
                                                            value={branch.id}
                                                        >
                                                            {branch.name}
                                                            {!branch.is_active
                                                                ? ' (Inactive)'
                                                                : ''}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>
                                        <Button type="submit" variant="outline">
                                            Apply filter
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <Badge variant="secondary">
                                {members.total}{' '}
                                {members.total === 1
                                    ? terminology.singularLower
                                    : terminology.pluralLower}
                            </Badge>
                        </div>
                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {members.data.length === 0 ? (
                                    <div className="flex flex-col items-center py-14 text-center">
                                        <UsersRound className="size-9 text-muted-foreground" />
                                        <h3 className="mt-4 font-semibold">
                                            No programme{' '}
                                            {terminology.pluralLower} found
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            There are no enrolments for the
                                            selected branch.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[850px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">
                                                        {terminology.singular}
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Branch
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Enrolment
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Start date
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        End date
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Profile
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {members.data.map((member) => (
                                                    <tr
                                                        key={member.id}
                                                        className="border-b last:border-0 hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium">
                                                                {
                                                                    member.player_name
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {member.internal_identifier ??
                                                                    'No internal ID'}
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {member.branch_name ??
                                                                'Not assigned'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    member.status ===
                                                                    'active'
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                                className="capitalize"
                                                            >
                                                                {member.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {member.starts_on}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {member.ends_on ??
                                                                '—'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    member.player_status ===
                                                                    'active'
                                                                        ? 'outline'
                                                                        : 'secondary'
                                                                }
                                                                className="capitalize"
                                                            >
                                                                {
                                                                    member.player_status
                                                                }
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {members.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                                        <p className="text-muted-foreground">
                                            Showing {members.from ?? 0}–
                                            {members.to ?? 0} of {members.total}
                                        </p>
                                        <div className="flex gap-2">
                                            {members.prev_page_url ? (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            members.prev_page_url
                                                        }
                                                        preserveScroll
                                                    >
                                                        Previous
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    disabled
                                                >
                                                    Previous
                                                </Button>
                                            )}
                                            {members.next_page_url ? (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            members.next_page_url
                                                        }
                                                        preserveScroll
                                                    >
                                                        Next
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    disabled
                                                >
                                                    Next
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                )}
            </main>
        </>
    );
}
