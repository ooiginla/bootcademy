import { Form, Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenText,
    MapPin,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
type ReenrollmentOptions = {
    cycles: Array<{
        id: number;
        name: string;
        is_active: boolean;
        terms: Array<{
            id: number;
            name: string;
            starts_on: string | null;
            ends_on: string | null;
        }>;
    }>;
    offerings: Array<{
        id: number;
        academy_cycle_id: number;
        programme_id: number;
        programme_name: string;
        branch_id: number | null;
        branch_name: string | null;
        cohorts: Array<{ id: number; name: string; branch_id: number | null }>;
    }>;
};

const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

function BulkEnrollmentActions({
    programmeId,
    selectedIds,
    clearSelection,
    options,
    branches,
}: {
    programmeId: number;
    selectedIds: number[];
    clearSelection: () => void;
    options: ReenrollmentOptions;
    branches: BranchOption[];
}) {
    const [completeOpen, setCompleteOpen] = useState(false);
    const [reenrollOpen, setReenrollOpen] = useState(false);
    const complete = useForm({ enrollment_ids: selectedIds });
    const reenroll = useForm({
        enrollment_ids: selectedIds,
        academy_cycle_id: '',
        programme_id: '',
        academy_cycle_term_id: '',
        branch_id: '',
        cohort_id: '',
    });
    const cycle = options.cycles.find(
        (item) => item.id === Number(reenroll.data.academy_cycle_id),
    );
    const programmes = useMemo(() => {
        const seen = new Set<number>();
        return options.offerings.filter((offering) => {
            if (
                offering.academy_cycle_id !==
                    Number(reenroll.data.academy_cycle_id) ||
                seen.has(offering.programme_id)
            )
                return false;
            seen.add(offering.programme_id);
            return true;
        });
    }, [options.offerings, reenroll.data.academy_cycle_id]);
    const matchingOffering =
        options.offerings.find(
            (offering) =>
                offering.academy_cycle_id ===
                    Number(reenroll.data.academy_cycle_id) &&
                offering.programme_id === Number(reenroll.data.programme_id) &&
                offering.branch_id === Number(reenroll.data.branch_id),
        ) ??
        options.offerings.find(
            (offering) =>
                offering.academy_cycle_id ===
                    Number(reenroll.data.academy_cycle_id) &&
                offering.programme_id === Number(reenroll.data.programme_id) &&
                offering.branch_id === null,
        );

    function submitComplete(event: FormEvent) {
        event.preventDefault();
        complete.setData('enrollment_ids', selectedIds);
        complete.patch(`/app/programmes/${programmeId}/enrollments/complete`, {
            preserveScroll: true,
            onSuccess: () => {
                setCompleteOpen(false);
                clearSelection();
            },
        });
    }

    function submitReenroll(event: FormEvent) {
        event.preventDefault();
        reenroll.transform((data) => ({
            ...data,
            enrollment_ids: selectedIds,
        }));
        reenroll.post(`/app/programmes/${programmeId}/enrollments/reenroll`, {
            preserveScroll: true,
            onSuccess: () => {
                setReenrollOpen(false);
                clearSelection();
                reenroll.reset();
            },
        });
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm text-muted-foreground">
                {selectedIds.length} selected
            </span>
            <Dialog open={completeOpen} onOpenChange={setCompleteOpen}>
                <DialogTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!selectedIds.length}
                    >
                        Mark completed
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Complete selected enrollments?
                        </DialogTitle>
                        <DialogDescription>
                            The enrollment history and invoices remain
                            available. Active cohort memberships will also be
                            completed.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitComplete} className="space-y-4">
                        <InputError message={complete.errors.enrollment_ids} />
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                disabled={complete.processing}
                            >
                                {complete.processing && <Spinner />}Complete{' '}
                                {selectedIds.length}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={reenrollOpen} onOpenChange={setReenrollOpen}>
                <DialogTrigger asChild>
                    <Button type="button" disabled={!selectedIds.length}>
                        <UserRoundCheck className="size-4" />
                        Enroll into new term
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Re-enroll selected members</DialogTitle>
                        <DialogDescription>
                            The prior enrollment is preserved. Applicable
                            compulsory and programme fees are invoiced once for
                            the selected term.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={submitReenroll}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>Cycle</Label>
                            <select
                                className={selectClass}
                                value={reenroll.data.academy_cycle_id}
                                onChange={(e) =>
                                    reenroll.setData({
                                        ...reenroll.data,
                                        academy_cycle_id: e.target.value,
                                        programme_id: '',
                                        academy_cycle_term_id: '',
                                        cohort_id: '',
                                    })
                                }
                                required
                            >
                                <option value="">Select cycle</option>
                                {options.cycles.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                        {item.is_active ? ' (Active)' : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={reenroll.errors.academy_cycle_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Programme</Label>
                            <select
                                className={selectClass}
                                value={reenroll.data.programme_id}
                                onChange={(e) =>
                                    reenroll.setData({
                                        ...reenroll.data,
                                        programme_id: e.target.value,
                                        cohort_id: '',
                                    })
                                }
                                required
                                disabled={!cycle}
                            >
                                <option value="">Select programme</option>
                                {programmes.map((item) => (
                                    <option
                                        key={item.programme_id}
                                        value={item.programme_id}
                                    >
                                        {item.programme_name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={reenroll.errors.programme_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Term</Label>
                            <select
                                className={selectClass}
                                value={reenroll.data.academy_cycle_term_id}
                                onChange={(e) =>
                                    reenroll.setData(
                                        'academy_cycle_term_id',
                                        e.target.value,
                                    )
                                }
                                required
                                disabled={!cycle}
                            >
                                <option value="">Select term</option>
                                {cycle?.terms.map((term) => (
                                    <option key={term.id} value={term.id}>
                                        {term.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={reenroll.errors.academy_cycle_term_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Branch</Label>
                            <select
                                className={selectClass}
                                value={reenroll.data.branch_id}
                                onChange={(e) =>
                                    reenroll.setData({
                                        ...reenroll.data,
                                        branch_id: e.target.value,
                                        cohort_id: '',
                                    })
                                }
                            >
                                <option value="">No specific branch</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={reenroll.errors.branch_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Cohort (optional)</Label>
                            <select
                                className={selectClass}
                                value={reenroll.data.cohort_id}
                                onChange={(e) =>
                                    reenroll.setData(
                                        'cohort_id',
                                        e.target.value,
                                    )
                                }
                                disabled={!matchingOffering}
                            >
                                <option value="">No cohort</option>
                                {matchingOffering?.cohorts
                                    .filter(
                                        (cohort) =>
                                            cohort.branch_id === null ||
                                            cohort.branch_id ===
                                                Number(reenroll.data.branch_id),
                                    )
                                    .map((cohort) => (
                                        <option
                                            key={cohort.id}
                                            value={cohort.id}
                                        >
                                            {cohort.name}
                                        </option>
                                    ))}
                            </select>
                            <InputError message={reenroll.errors.cohort_id} />
                        </div>
                        <InputError message={reenroll.errors.enrollment_ids} />
                        <DialogFooter className="sm:col-span-2">
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                disabled={reenroll.processing}
                            >
                                {reenroll.processing && <Spinner />}Enroll{' '}
                                {selectedIds.length}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default function ProgrammeDetails({
    programme,
    members,
    branch_options,
    selected_branch_id,
    selected_status,
    reenrollment_options,
    active_tab,
    can_manage,
}: {
    programme: Programme;
    members: Paginator;
    branch_options: BranchOption[];
    selected_branch_id: number | null;
    selected_status: 'all' | 'active' | 'completed';
    reenrollment_options: ReenrollmentOptions | null;
    active_tab: 'details' | 'members';
    can_manage: boolean;
}) {
    const terminology = useAcademyTerminology();
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const branchValue = selected_branch_id?.toString() ?? 'all';
    const tabHref = (tab: 'details' | 'members') =>
        `/app/programmes/${programme.id}?tab=${tab}&branch=${branchValue}&status=${selected_status}`;

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
                                        <div className="grid gap-1">
                                            <label
                                                htmlFor="programme-status-filter"
                                                className="text-sm font-medium"
                                            >
                                                Enrollment status
                                            </label>
                                            <select
                                                id="programme-status-filter"
                                                name="status"
                                                defaultValue={selected_status}
                                                className="h-9 min-w-40 rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="active">
                                                    Active
                                                </option>
                                                <option value="completed">
                                                    Completed
                                                </option>
                                                <option value="all">
                                                    All statuses
                                                </option>
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
                        {can_manage && reenrollment_options && (
                            <BulkEnrollmentActions
                                programmeId={programme.id}
                                selectedIds={selectedIds}
                                clearSelection={() => setSelectedIds([])}
                                options={reenrollment_options}
                                branches={branch_options}
                            />
                        )}
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
                                            There are no{' '}
                                            {selected_status === 'all'
                                                ? ''
                                                : selected_status}{' '}
                                            enrollments for the selected
                                            filters.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[850px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    {can_manage && (
                                                        <th className="w-12 px-4 py-3 font-medium">
                                                            <input
                                                                type="checkbox"
                                                                aria-label="Select all enrollments on this page"
                                                                checked={
                                                                    members.data
                                                                        .length >
                                                                        0 &&
                                                                    members.data.every(
                                                                        (
                                                                            member,
                                                                        ) =>
                                                                            selectedIds.includes(
                                                                                member.id,
                                                                            ),
                                                                    )
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setSelectedIds(
                                                                        event
                                                                            .target
                                                                            .checked
                                                                            ? Array.from(
                                                                                  new Set(
                                                                                      [
                                                                                          ...selectedIds,
                                                                                          ...members.data.map(
                                                                                              (
                                                                                                  member,
                                                                                              ) =>
                                                                                                  member.id,
                                                                                          ),
                                                                                      ],
                                                                                  ),
                                                                              )
                                                                            : selectedIds.filter(
                                                                                  (
                                                                                      id,
                                                                                  ) =>
                                                                                      !members.data.some(
                                                                                          (
                                                                                              member,
                                                                                          ) =>
                                                                                              member.id ===
                                                                                              id,
                                                                                      ),
                                                                              ),
                                                                    )
                                                                }
                                                                className="size-4 rounded border-input"
                                                            />
                                                        </th>
                                                    )}
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
                                                        {can_manage && (
                                                            <td className="px-4 py-3">
                                                                <input
                                                                    type="checkbox"
                                                                    aria-label={`Select ${member.player_name}`}
                                                                    checked={selectedIds.includes(
                                                                        member.id,
                                                                    )}
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setSelectedIds(
                                                                            event
                                                                                .target
                                                                                .checked
                                                                                ? [
                                                                                      ...selectedIds,
                                                                                      member.id,
                                                                                  ]
                                                                                : selectedIds.filter(
                                                                                      (
                                                                                          id,
                                                                                      ) =>
                                                                                          id !==
                                                                                          member.id,
                                                                                  ),
                                                                        )
                                                                    }
                                                                    className="size-4 rounded border-input"
                                                                />
                                                            </td>
                                                        )}
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
