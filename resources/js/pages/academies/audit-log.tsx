import { Form, Head, Link } from '@inertiajs/react';
import { Eye, ScrollText, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Actor = { id: number; name: string; email: string };
type Option = { value: string; label: string };
type AuditEntry = {
    id: number;
    actor: Actor | null;
    action: string;
    action_label: string;
    subject_type: string | null;
    subject_label: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown>;
    ip_address: string | null;
    created_at: string;
};
type Paginator = {
    data: AuditEntry[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Filters = {
    actor_user_id: number | null;
    action: string;
    subject_type: string;
    subject_id: number | null;
    date_from: string | null;
    date_to: string | null;
};

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

export default function AuditLog({
    logs,
    actors,
    actions,
    subject_types,
    filters,
}: {
    logs: Paginator;
    actors: Actor[];
    actions: Option[];
    subject_types: Option[];
    filters: Filters;
}) {
    const [selectedEntry, setSelectedEntry] = useState<AuditEntry | null>(null);

    return (
        <>
            <Head title="Audit log" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Academy administration
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold">Audit log</h1>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Review who performed sensitive academy actions and when.
                        Audit entries are read-only and cannot be edited or
                        deleted.
                    </p>
                </div>

                <Form
                    action="/app/settings/audit-log"
                    method="get"
                    className="mb-6 grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    {() => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-actor">User</Label>
                                <select
                                    id="audit-actor"
                                    name="actor_user_id"
                                    className={fieldClass}
                                    defaultValue={filters.actor_user_id ?? ''}
                                >
                                    <option value="">All users</option>
                                    {actors.map((actor) => (
                                        <option key={actor.id} value={actor.id}>
                                            {actor.name} · {actor.email}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-action">Action</Label>
                                <select
                                    id="audit-action"
                                    name="action"
                                    className={fieldClass}
                                    defaultValue={filters.action}
                                >
                                    <option value="">All actions</option>
                                    {actions.map((action) => (
                                        <option
                                            key={action.value}
                                            value={action.value}
                                        >
                                            {action.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-subject-type">
                                    Record type
                                </Label>
                                <select
                                    id="audit-subject-type"
                                    name="subject_type"
                                    className={fieldClass}
                                    defaultValue={filters.subject_type}
                                >
                                    <option value="">All record types</option>
                                    {subject_types.map((subject) => (
                                        <option
                                            key={subject.value}
                                            value={subject.value}
                                        >
                                            {subject.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-subject-id">
                                    Record ID
                                </Label>
                                <Input
                                    id="audit-subject-id"
                                    name="subject_id"
                                    type="number"
                                    min="1"
                                    defaultValue={filters.subject_id ?? ''}
                                    placeholder="Any record ID"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-date-from">From</Label>
                                <Input
                                    id="audit-date-from"
                                    name="date_from"
                                    type="date"
                                    defaultValue={filters.date_from ?? ''}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="audit-date-to">To</Label>
                                <Input
                                    id="audit-date-to"
                                    name="date_to"
                                    type="date"
                                    defaultValue={filters.date_to ?? ''}
                                />
                            </div>
                            <div className="flex items-end gap-2 lg:col-span-3">
                                <Button type="submit">Apply filters</Button>
                                <Button asChild type="button" variant="outline">
                                    <Link href="/app/settings/audit-log">
                                        Reset
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {logs.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <ScrollText className="mx-auto size-9 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No audit entries found
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Adjust the filters to review other activity.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[950px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Date</th>
                                            <th className="px-4 py-3">User</th>
                                            <th className="px-4 py-3">
                                                Action
                                            </th>
                                            <th className="px-4 py-3">
                                                Affected record
                                            </th>
                                            <th className="px-4 py-3">
                                                IP address
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Details
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {logs.data.map((entry) => (
                                            <tr
                                                key={entry.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {entry.created_at}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {entry.actor?.name ??
                                                            'System / API'}
                                                    </p>
                                                    {entry.actor && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {entry.actor.email}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="secondary">
                                                        {entry.action_label}
                                                    </Badge>
                                                    <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                        {entry.action}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {entry.subject_label ? (
                                                        <>
                                                            <span className="font-medium">
                                                                {
                                                                    entry.subject_label
                                                                }
                                                            </span>
                                                            {entry.subject_id && (
                                                                <span className="text-muted-foreground">
                                                                    {' '}
                                                                    #
                                                                    {
                                                                        entry.subject_id
                                                                    }
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {entry.ip_address ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setSelectedEntry(
                                                                entry,
                                                            )
                                                        }
                                                    >
                                                        <Eye className="size-4" />
                                                        View
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {logs.total > 0 && (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <p className="text-muted-foreground">
                            Showing {logs.from}–{logs.to} of {logs.total}{' '}
                            entries
                        </p>
                        <div className="flex gap-2">
                            <Button
                                asChild={Boolean(logs.prev_page_url)}
                                variant="outline"
                                size="sm"
                                disabled={!logs.prev_page_url}
                            >
                                {logs.prev_page_url ? (
                                    <Link href={logs.prev_page_url}>
                                        Previous
                                    </Link>
                                ) : (
                                    <span>Previous</span>
                                )}
                            </Button>
                            <Button
                                asChild={Boolean(logs.next_page_url)}
                                variant="outline"
                                size="sm"
                                disabled={!logs.next_page_url}
                            >
                                {logs.next_page_url ? (
                                    <Link href={logs.next_page_url}>Next</Link>
                                ) : (
                                    <span>Next</span>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </main>

            <Dialog
                open={selectedEntry !== null}
                onOpenChange={(open) => !open && setSelectedEntry(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-5 text-primary" />
                            Audit entry #{selectedEntry?.id}
                        </DialogTitle>
                        <DialogDescription>
                            Immutable activity information captured by the
                            platform.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedEntry && (
                        <div className="grid gap-4 text-sm">
                            <dl className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Action
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {selectedEntry.action_label}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Date
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {selectedEntry.created_at}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        User
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {selectedEntry.actor?.name ??
                                            'System / API'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Affected record
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {selectedEntry.subject_label
                                            ? `${selectedEntry.subject_label} #${selectedEntry.subject_id}`
                                            : 'None'}
                                    </dd>
                                </div>
                            </dl>
                            <div>
                                <h3 className="font-medium">Metadata</h3>
                                <pre className="mt-2 max-h-80 overflow-auto rounded-lg bg-muted p-4 font-mono text-xs whitespace-pre-wrap">
                                    {Object.keys(selectedEntry.metadata).length
                                        ? JSON.stringify(
                                              selectedEntry.metadata,
                                              null,
                                              2,
                                          )
                                        : 'No additional metadata was recorded.'}
                                </pre>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
