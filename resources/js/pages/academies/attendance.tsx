import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Clock3,
    MessageSquareText,
    Save,
    ShieldAlert,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Status = 'present' | 'absent' | 'late' | 'excused';
type RosterItem = {
    player_id: number;
    name: string;
    identifier: string | null;
    status: Status | null;
    notes: string | null;
    corrections: number;
};
const choices: Array<{ value: Status; label: string; active: string }> = [
    {
        value: 'present',
        label: 'Present',
        active: 'border-emerald-600 bg-emerald-600 text-white',
    },
    {
        value: 'absent',
        label: 'Absent',
        active: 'border-red-600 bg-red-600 text-white',
    },
    {
        value: 'late',
        label: 'Late',
        active: 'border-amber-500 bg-amber-500 text-white',
    },
    {
        value: 'excused',
        label: 'Excused',
        active: 'border-blue-600 bg-blue-600 text-white',
    },
];

export default function Attendance({
    session,
    roster,
    summary,
}: {
    session: {
        id: number;
        title: string;
        programme: string;
        branches: string[];
        coaches: string[];
        starts_at: string;
        status: string;
    };
    roster: RosterItem[];
    summary: {
        total: number;
        marked: number;
        present: number;
        absent: number;
        late: number;
        excused: number;
    };
}) {
    const [statuses, setStatuses] = useState<Record<number, Status | null>>(
        Object.fromEntries(roster.map((item) => [item.player_id, item.status])),
    );
    const [notes, setNotes] = useState<Record<number, string>>(
        Object.fromEntries(
            roster.map((item) => [item.player_id, item.notes ?? '']),
        ),
    );
    const [notePlayerId, setNotePlayerId] = useState<number | null>(null);
    const notePlayer = roster.find((item) => item.player_id === notePlayerId);
    const markAllPresent = () =>
        setStatuses(
            Object.fromEntries(
                roster.map((item) => [item.player_id, 'present']),
            ),
        );
    return (
        <>
            <Head title={`Attendance · ${session.title}`} />
            <main className="mx-auto w-full max-w-4xl p-4 sm:p-6">
                <Button asChild variant="ghost" className="mb-4">
                    <Link href="/app/sessions">
                        <ArrowLeft className="size-4" />
                        Back to sessions
                    </Link>
                </Button>
                <div className="mb-6">
                    <p className="text-sm font-medium text-primary">
                        Mobile attendance register
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold">
                        {session.title}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {session.starts_at} · {session.programme} ·{' '}
                        {session.branches.join(', ')}
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Assigned:{' '}
                        {session.coaches.join(', ') ||
                            'No coach/instructor assigned'}
                    </p>
                </div>
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {[
                        ['Roster', summary.total],
                        ['Marked', summary.marked],
                        ['Present', summary.present],
                        ['Absent', summary.absent],
                        ['Late', summary.late],
                        ['Excused', summary.excused],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">
                                    {label}
                                </p>
                                <p className="mt-1 text-xl font-semibold">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {session.status === 'cancelled' && (
                    <div className="mb-5 flex gap-2 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                        <ShieldAlert className="size-5" />
                        Attendance cannot be recorded for a cancelled session.
                    </div>
                )}
                <Form
                    action={`/app/sessions/${session.id}/attendance`}
                    method="put"
                    onSuccess={() => undefined}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Unmarked students remain unmarked.
                                        Select Absent when a student did not
                                        attend.
                                    </p>
                                    {errors.attendance && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.attendance}
                                        </p>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={markAllPresent}
                                    disabled={session.status === 'cancelled'}
                                >
                                    <Check className="size-4" />
                                    Mark all present
                                </Button>
                            </div>
                            <div className="overflow-x-auto rounded-xl border bg-card">
                                <div className="min-w-[650px]">
                                    <div className="grid grid-cols-[minmax(190px,1fr)_repeat(4,76px)_44px] items-center gap-2 border-b bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground">
                                        <span>Student</span>
                                        {choices.map((choice) => (
                                            <span
                                                key={choice.value}
                                                className="text-center"
                                            >
                                                {choice.label}
                                            </span>
                                        ))}
                                        <span className="text-center">
                                            Note
                                        </span>
                                    </div>
                                    {roster.map((item, index) => (
                                        <div
                                            key={item.player_id}
                                            className="grid grid-cols-[minmax(190px,1fr)_repeat(4,76px)_44px] items-center gap-2 border-b px-4 py-3 last:border-b-0"
                                        >
                                            <input
                                                type="hidden"
                                                name={`attendance[${index}][player_id]`}
                                                value={item.player_id}
                                            />
                                            <input
                                                type="hidden"
                                                name={`attendance[${index}][status]`}
                                                value={
                                                    statuses[item.player_id] ??
                                                    ''
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name={`attendance[${index}][notes]`}
                                                value={notes[item.player_id]}
                                            />
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <h2 className="truncate text-sm font-semibold">
                                                        {item.name}
                                                    </h2>
                                                    {item.corrections > 0 && (
                                                        <Badge
                                                            variant="outline"
                                                            className="shrink-0 px-1.5 py-0 text-[10px]"
                                                        >
                                                            Corrected{' '}
                                                            {item.corrections}×
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {item.identifier ??
                                                        `ID ${item.player_id}`}
                                                </p>
                                            </div>
                                            {choices.map((choice) => (
                                                <button
                                                    key={choice.value}
                                                    type="button"
                                                    disabled={
                                                        session.status ===
                                                        'cancelled'
                                                    }
                                                    onClick={() =>
                                                        setStatuses(
                                                            (current) => ({
                                                                ...current,
                                                                [item.player_id]:
                                                                    choice.value,
                                                            }),
                                                        )
                                                    }
                                                    aria-label={`${choice.label}: ${item.name}`}
                                                    className={`h-8 rounded-md border px-1.5 text-xs font-medium ${statuses[item.player_id] === choice.value ? choice.active : 'bg-background hover:bg-muted'}`}
                                                >
                                                    {choice.value ===
                                                        'late' && (
                                                        <Clock3 className="mr-1 inline size-3" />
                                                    )}
                                                    {choice.value ===
                                                        'absent' && (
                                                        <UserX className="mr-1 inline size-3" />
                                                    )}
                                                    {choice.label}
                                                </button>
                                            ))}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                aria-label={`${notes[item.player_id] ? 'Edit' : 'Add'} note for ${item.name}`}
                                                title={`${notes[item.player_id] ? 'Edit' : 'Add'} note`}
                                                onClick={() =>
                                                    setNotePlayerId(
                                                        item.player_id,
                                                    )
                                                }
                                                disabled={
                                                    session.status ===
                                                    'cancelled'
                                                }
                                            >
                                                <MessageSquareText
                                                    className={`size-4 ${notes[item.player_id] ? 'fill-primary/20 text-primary' : ''}`}
                                                />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            {roster.length === 0 && (
                                <div className="rounded-xl border py-14 text-center">
                                    <h2 className="font-semibold">
                                        No eligible players
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        There are no active programme
                                        enrollments in this session’s branch
                                        scope.
                                    </p>
                                </div>
                            )}
                            <div className="sticky bottom-3 mt-5 flex justify-end rounded-xl border bg-background/95 p-3 shadow-lg backdrop-blur">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        roster.length === 0 ||
                                        session.status === 'cancelled'
                                    }
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save className="size-4" />
                                    )}
                                    Save attendance
                                </Button>
                            </div>
                            <Dialog
                                open={notePlayerId !== null}
                                onOpenChange={(open) =>
                                    !open && setNotePlayerId(null)
                                }
                            >
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Attendance note
                                        </DialogTitle>
                                        <DialogDescription>
                                            Add an optional note for{' '}
                                            {notePlayer?.name}.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <label
                                        htmlFor="attendance-note"
                                        className="text-sm font-medium"
                                    >
                                        Note
                                    </label>
                                    <textarea
                                        id="attendance-note"
                                        className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        value={
                                            notePlayerId === null
                                                ? ''
                                                : notes[notePlayerId]
                                        }
                                        onChange={(event) => {
                                            if (notePlayerId !== null) {
                                                setNotes((current) => ({
                                                    ...current,
                                                    [notePlayerId]:
                                                        event.target.value,
                                                }));
                                            }
                                        }}
                                        maxLength={1000}
                                        placeholder="Reason for lateness, absence or other context"
                                    />
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                setNotePlayerId(null)
                                            }
                                        >
                                            Done
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}
