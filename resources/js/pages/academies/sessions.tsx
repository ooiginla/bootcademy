import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronsUpDown,
    Download,
    Pencil,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCoachTerminology } from '@/hooks/use-coach-terminology';
import type { Paginator } from './players';

type Option = { id: number; name: string };
type Session = {
    id: number;
    title: string;
    all_branches: boolean;
    branch_ids: number[];
    branch_names: string[];
    programme_id: number;
    programme_name: string;
    coach_membership_ids: number[];
    coach_names: string[];
    starts_at: string;
    ends_at: string;
    capacity: number | null;
    location: string | null;
    delivery_mode: 'physical' | 'virtual';
    meeting_link: string | null;
    notes: string | null;
    status: string;
};
const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

function SessionDialog({
    session,
    branches,
    programmes,
    coaches,
}: {
    session?: Session;
    branches: Option[];
    programmes: Option[];
    coaches: Option[];
}) {
    const [open, setOpen] = useState(false);
    const [allBranches, setAllBranches] = useState(
        session?.all_branches ?? false,
    );
    const [selectedBranchIds, setSelectedBranchIds] = useState<number[]>(
        session?.branch_ids ?? [],
    );
    const [selectedCoachIds, setSelectedCoachIds] = useState<number[]>(
        session?.coach_membership_ids ?? [],
    );
    const editing = Boolean(session);
    const terms = useCoachTerminology();
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={editing ? 'sm' : 'default'}
                    variant={editing ? 'ghost' : 'default'}
                    aria-label={editing ? `Edit ${session?.title}` : undefined}
                >
                    {editing ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {editing ? null : 'Add session'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit session' : 'Add session'}
                    </DialogTitle>
                    <DialogDescription>
                        Times are entered and displayed in the academy timezone.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/app/sessions/${session?.id}`
                            : '/app/sessions'
                    }
                    method={editing ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="session-title">Title</Label>
                                    <Input
                                        id="session-title"
                                        name="title"
                                        defaultValue={session?.title}
                                        required
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Branches</Label>
                                    <input
                                        type="hidden"
                                        name="all_branches"
                                        value={allBranches ? '1' : '0'}
                                    />
                                    {!allBranches &&
                                        selectedBranchIds.map((id) => (
                                            <input
                                                key={id}
                                                type="hidden"
                                                name="branch_ids[]"
                                                value={id}
                                            />
                                        ))}
                                    <DropdownMenu modal={false}>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full justify-between font-normal"
                                            >
                                                {allBranches
                                                    ? 'All branches'
                                                    : selectedBranchIds.length ===
                                                        0
                                                      ? 'Select branches'
                                                      : `${selectedBranchIds.length} branch${selectedBranchIds.length === 1 ? '' : 'es'} selected`}
                                                <ChevronsUpDown className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            className="w-72"
                                            align="start"
                                        >
                                            <DropdownMenuLabel>
                                                Choose branches
                                            </DropdownMenuLabel>
                                            <DropdownMenuCheckboxItem
                                                checked={allBranches}
                                                onCheckedChange={(checked) =>
                                                    setAllBranches(
                                                        checked === true,
                                                    )
                                                }
                                                onSelect={(event) =>
                                                    event.preventDefault()
                                                }
                                            >
                                                All branches
                                            </DropdownMenuCheckboxItem>
                                            <DropdownMenuSeparator />
                                            {branches.map((branch) => (
                                                <DropdownMenuCheckboxItem
                                                    key={branch.id}
                                                    checked={selectedBranchIds.includes(
                                                        branch.id,
                                                    )}
                                                    disabled={allBranches}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedBranchIds(
                                                            (current) =>
                                                                checked
                                                                    ? [
                                                                          ...current,
                                                                          branch.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              branch.id,
                                                                      ),
                                                        )
                                                    }
                                                    onSelect={(event) =>
                                                        event.preventDefault()
                                                    }
                                                >
                                                    {branch.name}
                                                </DropdownMenuCheckboxItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <InputError message={errors.branch_ids} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Programme</Label>
                                    <select
                                        name="programme_id"
                                        defaultValue={
                                            session?.programme_id ?? ''
                                        }
                                        className={fieldClass}
                                        required
                                    >
                                        <option value="">
                                            Select programme
                                        </option>
                                        {programmes.map((x) => (
                                            <option key={x.id} value={x.id}>
                                                {x.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.programme_id} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>{terms.plural}</Label>
                                    {selectedCoachIds.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="coach_membership_ids[]"
                                            value={id}
                                        />
                                    ))}
                                    <DropdownMenu modal={false}>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full justify-between font-normal"
                                            >
                                                {selectedCoachIds.length === 0
                                                    ? `Select ${terms.pluralLower}`
                                                    : `${selectedCoachIds.length} ${selectedCoachIds.length === 1 ? terms.singularLower : terms.pluralLower} selected`}
                                                <ChevronsUpDown className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            className="w-72"
                                            align="start"
                                        >
                                            <DropdownMenuLabel>
                                                Choose {terms.pluralLower}
                                            </DropdownMenuLabel>
                                            {coaches.map((coach) => (
                                                <DropdownMenuCheckboxItem
                                                    key={coach.id}
                                                    checked={selectedCoachIds.includes(
                                                        coach.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedCoachIds(
                                                            (current) =>
                                                                checked
                                                                    ? [
                                                                          ...current,
                                                                          coach.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              coach.id,
                                                                      ),
                                                        )
                                                    }
                                                    onSelect={(event) =>
                                                        event.preventDefault()
                                                    }
                                                >
                                                    {coach.name}
                                                </DropdownMenuCheckboxItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <p className="text-xs text-muted-foreground">
                                        Leave all unchecked to keep the session
                                        unassigned.
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Capacity</Label>
                                    <Input
                                        name="capacity"
                                        type="number"
                                        min="1"
                                        defaultValue={session?.capacity ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Starts</Label>
                                    <Input
                                        name="starts_at"
                                        type="datetime-local"
                                        defaultValue={session?.starts_at}
                                        required
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Ends</Label>
                                    <Input
                                        name="ends_at"
                                        type="datetime-local"
                                        defaultValue={session?.ends_at}
                                        required
                                    />
                                    <InputError message={errors.ends_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Location</Label>
                                    <Input
                                        name="location"
                                        defaultValue={session?.location ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="session-delivery-mode">
                                        Delivery mode
                                    </Label>
                                    <select
                                        id="session-delivery-mode"
                                        name="delivery_mode"
                                        defaultValue={
                                            session?.delivery_mode ?? 'physical'
                                        }
                                        className={fieldClass}
                                    >
                                        <option value="physical">
                                            Physical
                                        </option>
                                        <option value="virtual">Virtual</option>
                                    </select>
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="session-meeting-link">
                                        Meeting link (optional)
                                    </Label>
                                    <Input
                                        id="session-meeting-link"
                                        name="meeting_link"
                                        type="url"
                                        placeholder="https://meet.example.com/session"
                                        defaultValue={
                                            session?.meeting_link ?? ''
                                        }
                                    />
                                    <InputError message={errors.meeting_link} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Status</Label>
                                    <select
                                        name="status"
                                        defaultValue={
                                            session?.status ?? 'scheduled'
                                        }
                                        className={fieldClass}
                                    >
                                        <option value="scheduled">
                                            Scheduled
                                        </option>
                                        <option value="completed">
                                            Completed
                                        </option>
                                        <option value="cancelled">
                                            Cancelled
                                        </option>
                                    </select>
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Notes</Label>
                                    <textarea
                                        name="notes"
                                        defaultValue={session?.notes ?? ''}
                                        className={`${fieldClass} min-h-24 py-2`}
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing && <Spinner />}
                                    {editing
                                        ? 'Save session'
                                        : 'Create session'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function time(value: string) {
    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}
function date(value: string) {
    return new Date(value).toLocaleDateString([], { dateStyle: 'medium' });
}

export default function Sessions({
    sessions,
    calendar_sessions,
    branches,
    programmes,
    coaches,
    filters,
    active_tab,
    calendar,
    can_manage,
}: {
    sessions: Paginator<Session>;
    calendar_sessions: Session[];
    branches: Option[];
    programmes: Option[];
    coaches: Option[];
    filters: {
        branch_id: number | null;
        programme_id: number | null;
        coach_id: number | null;
        month: string;
    };
    active_tab: 'list' | 'calendar';
    calendar: { start: string; end: string; month: string };
    can_manage: boolean;
}) {
    const days: Date[] = [];
    for (
        let d = new Date(`${calendar.start}T00:00:00`),
            end = new Date(`${calendar.end}T00:00:00`);
        d <= end;
        d.setDate(d.getDate() + 1)
    )
        days.push(new Date(d));
    const query = new URLSearchParams({
        month: filters.month,
        ...(filters.branch_id ? { branch_id: String(filters.branch_id) } : {}),
        ...(filters.programme_id
            ? { programme_id: String(filters.programme_id) }
            : {}),
        ...(filters.coach_id ? { coach_id: String(filters.coach_id) } : {}),
    });
    return (
        <>
            <Head title="Sessions" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-6 flex items-end justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy operations
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Sessions
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Schedule activities and preview exactly when they
                            happen.
                        </p>
                    </div>
                    {can_manage && (
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <a href="/app/reports/attendance.csv">
                                    <Download className="size-4" />
                                    Attendance CSV
                                </a>
                            </Button>
                            <SessionDialog
                                branches={branches}
                                programmes={programmes}
                                coaches={coaches}
                            />
                        </div>
                    )}
                </div>
                <Form
                    action="/app/sessions"
                    method="get"
                    className="mb-5 grid items-end gap-3 md:grid-cols-5"
                >
                    {() => (
                        <>
                            <input
                                type="hidden"
                                name="tab"
                                value={active_tab}
                            />
                            <input
                                type="month"
                                name="month"
                                defaultValue={filters.month}
                                className={fieldClass}
                            />
                            <select
                                name="branch_id"
                                defaultValue={filters.branch_id ?? ''}
                                className={fieldClass}
                            >
                                <option value="">All branches</option>
                                {branches.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="programme_id"
                                defaultValue={filters.programme_id ?? ''}
                                className={fieldClass}
                            >
                                <option value="">All programmes</option>
                                {programmes.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="coach_id"
                                defaultValue={filters.coach_id ?? ''}
                                className={fieldClass}
                            >
                                <option value="">All coaches</option>
                                {coaches.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                            <Button variant="outline">Apply filters</Button>
                        </>
                    )}
                </Form>
                <div className="mb-5 flex w-fit gap-1 rounded-lg border bg-muted/40 p-1">
                    <Button
                        asChild
                        size="sm"
                        variant={active_tab === 'list' ? 'default' : 'ghost'}
                    >
                        <Link href={`/app/sessions?tab=list&${query}`}>
                            List
                        </Link>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        variant={
                            active_tab === 'calendar' ? 'default' : 'ghost'
                        }
                    >
                        <Link href={`/app/sessions?tab=calendar&${query}`}>
                            Calendar
                        </Link>
                    </Button>
                </div>
                {active_tab === 'list' ? (
                    <Card className="overflow-hidden py-0">
                        <CardContent className="p-0">
                            {sessions.data.length ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[900px] text-left text-sm">
                                        <thead className="border-b bg-muted/50">
                                            <tr>
                                                <th className="p-4">Session</th>
                                                <th>When</th>
                                                <th>Branch / programme</th>
                                                <th>Coach</th>
                                                <th>Status</th>
                                                <th className="text-right">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {sessions.data.map((s) => (
                                                <tr
                                                    key={s.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="p-4">
                                                        <b>{s.title}</b>
                                                        <p className="text-xs text-muted-foreground">
                                                            {s.delivery_mode ===
                                                            'virtual'
                                                                ? 'Virtual'
                                                                : (s.location ??
                                                                  'Physical · No location')}
                                                        </p>
                                                        {s.meeting_link && (
                                                            <a
                                                                href={
                                                                    s.meeting_link
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-xs text-primary hover:underline"
                                                            >
                                                                Open meeting
                                                                link
                                                            </a>
                                                        )}
                                                    </td>
                                                    <td>
                                                        {date(s.starts_at)} ·{' '}
                                                        {time(s.starts_at)}–
                                                        {time(s.ends_at)}
                                                    </td>
                                                    <td>
                                                        {s.all_branches
                                                            ? 'All branches'
                                                            : s.branch_names.join(
                                                                  ', ',
                                                              )}
                                                        <p className="text-xs text-muted-foreground">
                                                            {s.programme_name}
                                                        </p>
                                                    </td>
                                                    <td>
                                                        {s.coach_names.join(
                                                            ', ',
                                                        ) || 'Unassigned'}
                                                    </td>
                                                    <td>
                                                        <Badge
                                                            variant={
                                                                s.status ===
                                                                'cancelled'
                                                                    ? 'destructive'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {s.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <Link
                                                                    href={`/app/sessions/${s.id}/attendance`}
                                                                >
                                                                    Attendance
                                                                </Link>
                                                            </Button>
                                                            {can_manage && (
                                                                <SessionDialog
                                                                    session={s}
                                                                    branches={
                                                                        branches
                                                                    }
                                                                    programmes={
                                                                        programmes
                                                                    }
                                                                    coaches={
                                                                        coaches
                                                                    }
                                                                />
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="py-14 text-center">
                                    <CalendarDays className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No sessions found
                                    </h2>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-7 overflow-hidden rounded-xl border bg-card">
                        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(
                            (x) => (
                                <div
                                    key={x}
                                    className="border-b p-2 text-center text-xs font-medium"
                                >
                                    {x}
                                </div>
                            ),
                        )}
                        {days.map((day) => {
                            const key = day.toISOString().slice(0, 10);
                            const items = calendar_sessions.filter(
                                (s) => s.starts_at.slice(0, 10) === key,
                            );
                            return (
                                <div
                                    key={key}
                                    className="min-h-28 border-r border-b p-2"
                                >
                                    <span className="text-xs text-muted-foreground">
                                        {day.getDate()}
                                    </span>
                                    {items.map((s) => (
                                        <Link
                                            key={s.id}
                                            href={`/app/sessions/${s.id}/attendance`}
                                            className="mt-1 rounded bg-primary/10 p-1 text-xs text-primary"
                                        >
                                            <b>{time(s.starts_at)}</b> {s.title}
                                            <div>
                                                {s.all_branches
                                                    ? 'All branches'
                                                    : s.branch_names.join(', ')}
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            );
                        })}
                    </div>
                )}
            </main>
        </>
    );
}
