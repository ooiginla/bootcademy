import { Head, router, useForm } from '@inertiajs/react';
import { BellRing, Megaphone, Send, Settings2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/money';

type Due = {
    id: number;
    number: string;
    player_name: string;
    fee_name: string;
    programme: string | null;
    branch: string | null;
    due_on: string;
    days_until_due: number;
    balance: string;
    currency: string;
    recipient_count: number;
};
type Renewal = {
    id: number;
    player_name: string;
    programme: string;
    branch: string | null;
    ends_on: string;
    days_until_end: number;
    recipient_count: number;
};
type Announcement = {
    id: number;
    title: string;
    body: string;
    audience_type: string;
    status: string;
    published_at: string | null;
    created_at: string;
};
type Delivery = {
    id: number;
    type: string;
    recipient_email: string;
    recipient_name: string | null;
    status: string;
    queued_at: string;
    sent_at: string | null;
};

const tabs = [
    'Announcements',
    'Payment due',
    'Renewals',
    'Delivery history',
    'Settings',
] as const;

export default function Communications({
    announcements,
    payment_due,
    renewals,
    deliveries,
    settings,
    branches,
    programmes,
    can_manage_announcements,
}: {
    announcements: Announcement[];
    payment_due: Due[];
    renewals: Renewal[];
    deliveries: Delivery[];
    settings: {
        automatic_payment_reminders: boolean;
        payment_reminder_days: number[];
        automatic_renewal_reminders: boolean;
        renewal_reminder_days: number[];
    };
    branches: { id: number; name: string }[];
    programmes: { id: number; name: string }[];
    can_manage_announcements: boolean;
}) {
    const [tab, setTab] = useState<(typeof tabs)[number]>('Announcements');
    const [open, setOpen] = useState(false);
    const announcement = useForm({
        title: '',
        body: '',
        audience_type: 'all',
        branch_id: '',
        programme_id: '',
        publish_now: true,
        expires_at: '',
    });
    const reminder = useForm({
        automatic_payment_reminders: settings.automatic_payment_reminders,
        payment_reminder_days: settings.payment_reminder_days.join(', '),
        automatic_renewal_reminders: settings.automatic_renewal_reminders,
        renewal_reminder_days: settings.renewal_reminder_days.join(', '),
    });
    const submitAnnouncement = (event: React.FormEvent) => {
        event.preventDefault();
        announcement.post('/app/communications/announcements', {
            onSuccess: () => {
                setOpen(false);
                announcement.reset();
            },
        });
    };
    const saveSettings = (event: React.FormEvent) => {
        event.preventDefault();
        const days = (value: string) =>
            value.split(',').map(Number).filter(Number.isFinite);
        router.patch('/app/communications/settings', {
            automatic_payment_reminders:
                reminder.data.automatic_payment_reminders,
            payment_reminder_days: days(reminder.data.payment_reminder_days),
            automatic_renewal_reminders:
                reminder.data.automatic_renewal_reminders,
            renewal_reminder_days: days(reminder.data.renewal_reminder_days),
        });
    };

    return (
        <>
            <Head title="Communications" />
            <main className="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold">Communications</h1>
                        <p className="text-sm text-muted-foreground">
                            Announcements, upcoming payments, renewals and
                            delivery records in one place.
                        </p>
                    </div>
                    {can_manage_announcements && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Megaphone className="size-4" />
                                    New announcement
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Create announcement
                                    </DialogTitle>
                                </DialogHeader>
                                <form
                                    onSubmit={submitAnnouncement}
                                    className="space-y-4"
                                >
                                    <div>
                                        <Label>Title</Label>
                                        <Input
                                            value={announcement.data.title}
                                            onChange={(e) =>
                                                announcement.setData(
                                                    'title',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label>Message</Label>
                                        <textarea
                                            className="min-h-36 w-full rounded-md border bg-background p-3 text-sm"
                                            value={announcement.data.body}
                                            onChange={(e) =>
                                                announcement.setData(
                                                    'body',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label>Audience</Label>
                                            <select
                                                className="h-10 w-full rounded-md border bg-background px-3"
                                                value={
                                                    announcement.data
                                                        .audience_type
                                                }
                                                onChange={(e) =>
                                                    announcement.setData(
                                                        'audience_type',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="all">
                                                    All active families
                                                </option>
                                                <option value="branch">
                                                    Specific branch
                                                </option>
                                                <option value="programme">
                                                    Specific programme
                                                </option>
                                            </select>
                                        </div>
                                        {announcement.data.audience_type ===
                                            'branch' && (
                                            <div>
                                                <Label>Branch</Label>
                                                <select
                                                    className="h-10 w-full rounded-md border bg-background px-3"
                                                    value={
                                                        announcement.data
                                                            .branch_id
                                                    }
                                                    onChange={(e) =>
                                                        announcement.setData(
                                                            'branch_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    required
                                                >
                                                    <option value="">
                                                        Select branch
                                                    </option>
                                                    {branches.map((x) => (
                                                        <option
                                                            key={x.id}
                                                            value={x.id}
                                                        >
                                                            {x.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                        {announcement.data.audience_type ===
                                            'programme' && (
                                            <div>
                                                <Label>Programme</Label>
                                                <select
                                                    className="h-10 w-full rounded-md border bg-background px-3"
                                                    value={
                                                        announcement.data
                                                            .programme_id
                                                    }
                                                    onChange={(e) =>
                                                        announcement.setData(
                                                            'programme_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    required
                                                >
                                                    <option value="">
                                                        Select programme
                                                    </option>
                                                    {programmes.map((x) => (
                                                        <option
                                                            key={x.id}
                                                            value={x.id}
                                                        >
                                                            {x.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={
                                                announcement.data.publish_now
                                            }
                                            onChange={(e) =>
                                                announcement.setData(
                                                    'publish_now',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        Publish and email now
                                    </label>
                                    <Button
                                        type="submit"
                                        disabled={announcement.processing}
                                    >
                                        <Send className="size-4" />
                                        {announcement.processing
                                            ? 'Sending…'
                                            : 'Save announcement'}
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
                <div className="flex w-full min-w-0 gap-2 overflow-x-auto border-b">
                    {tabs.map((item) => (
                        <button
                            key={item}
                            onClick={() => setTab(item)}
                            className={`border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap ${tab === item ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'}`}
                        >
                            {item}
                        </button>
                    ))}
                </div>
                {tab === 'Announcements' && (
                    <Card>
                        <CardHeader>
                            <h2 className="font-semibold">Announcements</h2>
                        </CardHeader>
                        <CardContent>
                            {announcements.length === 0 ? (
                                <Empty text="No announcements yet." />
                            ) : (
                                <div className="divide-y">
                                    {announcements.map((item) => (
                                        <div key={item.id} className="py-4">
                                            <div className="flex justify-between gap-3">
                                                <div>
                                                    <p className="font-medium">
                                                        {item.title}
                                                    </p>
                                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                        {item.body}
                                                    </p>
                                                </div>
                                                <span className="h-fit rounded-full bg-muted px-2 py-1 text-xs capitalize">
                                                    {item.status}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Audience: {item.audience_type}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
                {tab === 'Payment due' && (
                    <DataTable
                        headers={[
                            'Student / player',
                            'Fee',
                            'Due',
                            'Outstanding',
                            'Contact',
                            '',
                        ]}
                        rows={payment_due.map((item) => [
                            item.player_name,
                            <span>
                                <strong>{item.fee_name}</strong>
                                <small className="block text-muted-foreground">
                                    {item.number} · {item.programme}
                                </small>
                            </span>,
                            <DueLabel
                                days={item.days_until_due}
                                date={item.due_on}
                            />,
                            formatCurrency(item.currency, item.balance),
                            item.recipient_count
                                ? `${item.recipient_count} email contact(s)`
                                : 'No guardian email',
                            <Button
                                key="send"
                                size="sm"
                                variant="outline"
                                disabled={!item.recipient_count}
                                onClick={() =>
                                    router.post(
                                        `/app/communications/invoices/${item.id}/remind`,
                                    )
                                }
                            >
                                Remind
                            </Button>,
                        ])}
                        empty="No unpaid invoices due within 45 days."
                    />
                )}
                {tab === 'Renewals' && (
                    <DataTable
                        headers={[
                            'Student / player',
                            'Programme',
                            'Ends',
                            'Contact',
                            '',
                        ]}
                        rows={renewals.map((item) => [
                            item.player_name,
                            <span>
                                {item.programme}
                                <small className="block text-muted-foreground">
                                    {item.branch}
                                </small>
                            </span>,
                            <DueLabel
                                days={item.days_until_end}
                                date={item.ends_on}
                            />,
                            item.recipient_count
                                ? `${item.recipient_count} email contact(s)`
                                : 'No guardian email',
                            <Button
                                key="send"
                                size="sm"
                                variant="outline"
                                disabled={!item.recipient_count}
                                onClick={() =>
                                    router.post(
                                        `/app/communications/enrollments/${item.id}/remind`,
                                    )
                                }
                            >
                                Remind
                            </Button>,
                        ])}
                        empty="No enrolments ending within 60 days."
                    />
                )}
                {tab === 'Delivery history' && (
                    <DataTable
                        headers={['Type', 'Recipient', 'Queued', 'Status']}
                        rows={deliveries.map((item) => [
                            item.type.replaceAll('_', ' '),
                            <span>
                                {item.recipient_name}
                                <small className="block text-muted-foreground">
                                    {item.recipient_email}
                                </small>
                            </span>,
                            new Date(item.queued_at).toLocaleString(),
                            <span className="capitalize">{item.status}</span>,
                        ])}
                        empty="No notifications have been queued."
                    />
                )}
                {tab === 'Settings' && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2 className="size-5" />
                                <h2 className="font-semibold">
                                    Automated reminder settings
                                </h2>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={saveSettings}
                                className="max-w-2xl space-y-6"
                            >
                                <SettingToggle
                                    label="Automatic payment reminders"
                                    description="Email linked contacts before an unpaid invoice or installment is due."
                                    checked={
                                        reminder.data
                                            .automatic_payment_reminders
                                    }
                                    onChange={(value) =>
                                        reminder.setData(
                                            'automatic_payment_reminders',
                                            value,
                                        )
                                    }
                                />
                                <div>
                                    <Label>
                                        Payment reminder days before due date
                                    </Label>
                                    <Input
                                        value={
                                            reminder.data.payment_reminder_days
                                        }
                                        onChange={(e) =>
                                            reminder.setData(
                                                'payment_reminder_days',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="14, 7, 1, 0"
                                    />
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Comma-separated. 0 means on the due
                                        date.
                                    </p>
                                </div>
                                <SettingToggle
                                    label="Automatic renewal reminders"
                                    description="Email linked contacts before an active enrolment ends."
                                    checked={
                                        reminder.data
                                            .automatic_renewal_reminders
                                    }
                                    onChange={(value) =>
                                        reminder.setData(
                                            'automatic_renewal_reminders',
                                            value,
                                        )
                                    }
                                />
                                <div>
                                    <Label>
                                        Renewal reminder days before end date
                                    </Label>
                                    <Input
                                        value={
                                            reminder.data.renewal_reminder_days
                                        }
                                        onChange={(e) =>
                                            reminder.setData(
                                                'renewal_reminder_days',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="30, 14, 7"
                                    />
                                </div>
                                <Button type="submit">
                                    Save reminder settings
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}

function Empty({ text }: { text: string }) {
    return (
        <div className="py-12 text-center text-sm text-muted-foreground">
            <BellRing className="mx-auto mb-3 size-8" />
            {text}
        </div>
    );
}
function DueLabel({ days, date }: { days: number; date: string }) {
    const roundedDays = days < 0 ? Math.floor(days) : Math.ceil(days);

    return (
        <span
            className={
                roundedDays < 0
                    ? 'font-medium text-red-600'
                    : roundedDays <= 7
                      ? 'font-medium text-amber-600'
                      : ''
            }
        >
            {new Date(`${date}T00:00:00`).toLocaleDateString()}
            <small className="block">
                {roundedDays < 0
                    ? `${Math.abs(roundedDays)} days overdue`
                    : roundedDays === 0
                      ? 'Due today'
                      : `In ${roundedDays} days`}
            </small>
        </span>
    );
}
function DataTable({
    headers,
    rows,
    empty,
}: {
    headers: string[];
    rows: React.ReactNode[][];
    empty: string;
}) {
    return (
        <Card>
            <CardContent className="p-0">
                {rows.length === 0 ? (
                    <Empty text={empty} />
                ) : (
                    <div className="w-full min-w-0 overflow-x-auto">
                        <table className="w-full min-w-max text-sm">
                            <thead className="border-b bg-muted/40 text-left">
                                {' '}
                                <tr>
                                    {headers.map((header) => (
                                        <th
                                            key={header}
                                            className="px-4 py-3 font-medium"
                                        >
                                            {header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((row, index) => (
                                    <tr key={index}>
                                        {row.map((cell, cellIndex) => (
                                            <td
                                                key={cellIndex}
                                                className="px-4 py-3 align-top"
                                            >
                                                {cell}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
function SettingToggle({
    label,
    description,
    checked,
    onChange,
}: {
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label className="flex items-start gap-3 rounded-xl border p-4">
            <input
                type="checkbox"
                className="mt-1 size-4"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            <span>
                <strong className="block text-sm">{label}</strong>
                <span className="text-sm text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}
