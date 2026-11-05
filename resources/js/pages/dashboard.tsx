import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    CalendarDays,
    ClipboardCheck,
    Download,
    GraduationCap,
    ReceiptText,
    TrendingDown,
    TrendingUp,
    UserPlus,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import { formatCurrency } from '@/lib/money';

type Branch = { id: number; name: string };
type Operational = {
    currency: string;
    active_players: number;
    new_enrollments: number;
    sessions: number;
    attendance_marked: number;
    attendance_rate: number | null;
    billed: string;
    outstanding: string;
    collected: string;
    reversed: string;
    net_collected: string;
    expenses: string;
    net_cash: string;
    aging: {
        not_due: string;
        days_1_30: string;
        days_31_60: string;
        days_61_plus: string;
    };
    branch_metrics: Array<{
        id: number;
        name: string;
        active_players: number;
        collected: string;
        expenses: string;
    }>;
};

export default function Dashboard({
    operational,
    branches,
    filters,
    today,
}: {
    operational: Operational | null;
    branches: Branch[];
    filters: { date_from: string; date_to: string; branch_id: number | null };
    today: string;
}) {
    const terminology = useAcademyTerminology();
    const { auth, academy } = usePage().props;
    const firstName = auth.user.name.trim().split(/\s+/)[0] || 'there';

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="relative overflow-hidden rounded-3xl bg-primary px-6 py-7 text-primary-foreground shadow-sm sm:px-8">
                    <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-sm text-primary-foreground/75">
                                {academy?.name}
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold sm:text-3xl">
                                Welcome, {firstName}
                            </h1>
                            <p className="mt-2 text-sm text-primary-foreground/80">
                                Operational and cash activity for the selected
                                reporting period.
                            </p>
                        </div>
                        {operational && (
                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="secondary" size="sm">
                                    <a href="/app/reports/invoices.csv">
                                        <Download className="size-4" />
                                        Invoices
                                    </a>
                                </Button>
                                <Button asChild variant="secondary" size="sm">
                                    <a href="/app/reports/payments.csv">
                                        <Download className="size-4" />
                                        Payments
                                    </a>
                                </Button>
                                <Button asChild variant="secondary" size="sm">
                                    <a href="/app/reports/expenses.csv">
                                        <Download className="size-4" />
                                        Expenses
                                    </a>
                                </Button>
                                <Button asChild variant="secondary" size="sm">
                                    <a href="/app/reports/attendance.csv">
                                        <Download className="size-4" />
                                        Attendance
                                    </a>
                                </Button>
                            </div>
                        )}
                    </div>
                </section>

                {!operational ? (
                    <Card>
                        <CardContent className="p-8 text-center">
                            <h2 className="font-semibold">
                                Your workspace is ready
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Operational and finance summaries are available
                                to academy owners and administrators.
                            </p>
                            <Button asChild className="mt-5">
                                <Link href="/app/sessions">
                                    View your sessions
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Form
                            action="/app/dashboard"
                            method="get"
                            className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                        >
                            {() => (
                                <>
                                    <div className="grid gap-1">
                                        <Label>Branch</Label>
                                        <select
                                            name="branch_id"
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                            defaultValue={
                                                filters.branch_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                All branches
                                            </option>
                                            {branches.map((branch) => (
                                                <option
                                                    key={branch.id}
                                                    value={branch.id}
                                                >
                                                    {branch.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>From</Label>
                                        <Input
                                            type="date"
                                            name="date_from"
                                            defaultValue={filters.date_from}
                                            max={today}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>To</Label>
                                        <Input
                                            type="date"
                                            name="date_to"
                                            defaultValue={filters.date_to}
                                            max={today}
                                        />
                                    </div>
                                    <Button type="submit">Apply filters</Button>
                                </>
                            )}
                        </Form>

                        <section>
                            <h2 className="mb-3 text-lg font-semibold">
                                Operations
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {(
                                    [
                                        [
                                            `Active ${terminology.pluralLower}`,
                                            operational.active_players,
                                            GraduationCap,
                                            `Active as at ${filters.date_to}`,
                                        ],
                                        [
                                            'New enrollments',
                                            operational.new_enrollments,
                                            UserPlus,
                                            'Enrollment start date in period',
                                        ],
                                        [
                                            'Sessions',
                                            operational.sessions,
                                            CalendarDays,
                                            'Scheduled in selected period',
                                        ],
                                        [
                                            'Attendance rate',
                                            operational.attendance_rate === null
                                                ? '—'
                                                : `${operational.attendance_rate}%`,
                                            ClipboardCheck,
                                            `${operational.attendance_marked} marked records`,
                                        ],
                                    ] as Array<
                                        [
                                            string,
                                            string | number,
                                            LucideIcon,
                                            string,
                                        ]
                                    >
                                ).map(([label, value, Icon, helper]) => (
                                    <Card key={String(label)}>
                                        <CardContent className="p-5">
                                            <div className="flex justify-between">
                                                <div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {String(label)}
                                                    </p>
                                                    <p className="mt-2 text-3xl font-semibold">
                                                        {String(value)}
                                                    </p>
                                                </div>
                                                <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                                    <Icon className="size-5" />
                                                </span>
                                            </div>
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                {String(helper)}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </section>

                        <section>
                            <h2 className="mb-3 text-lg font-semibold">
                                Finance
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {(
                                    [
                                        [
                                            'Billed',
                                            operational.billed,
                                            ReceiptText,
                                            'Invoices issued in period',
                                            'bg-violet-50',
                                        ],
                                        [
                                            'Outstanding',
                                            operational.outstanding,
                                            Banknote,
                                            'Still collectible',
                                            'bg-amber-50',
                                        ],
                                        [
                                            'Net collected',
                                            operational.net_collected,
                                            TrendingUp,
                                            `${formatCurrency(operational.currency, operational.reversed)} reversed`,
                                            'bg-emerald-50',
                                        ],
                                        [
                                            'Expenses',
                                            operational.expenses,
                                            WalletCards,
                                            'Costs and write-offs',
                                            'bg-red-50',
                                        ],
                                    ] as Array<
                                        [
                                            string,
                                            string,
                                            LucideIcon,
                                            string,
                                            string,
                                        ]
                                    >
                                ).map(
                                    ([label, value, Icon, helper, colour]) => (
                                        <Card
                                            key={String(label)}
                                            className={String(colour)}
                                        >
                                            <CardContent className="p-5">
                                                <div className="flex justify-between">
                                                    <div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {String(label)}
                                                        </p>
                                                        <p className="mt-2 text-2xl font-semibold">
                                                            {formatCurrency(
                                                                operational.currency,
                                                                String(value),
                                                            )}
                                                        </p>
                                                    </div>
                                                    <Icon className="size-5 text-muted-foreground" />
                                                </div>
                                                <p className="mt-3 text-xs text-muted-foreground">
                                                    {String(helper)}
                                                </p>
                                            </CardContent>
                                        </Card>
                                    ),
                                )}
                            </div>
                            <Card className="mt-3">
                                <CardContent className="flex items-center justify-between p-5">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Net cash movement
                                        </p>
                                        <p className="mt-1 text-2xl font-semibold">
                                            {formatCurrency(
                                                operational.currency,
                                                operational.net_cash,
                                            )}
                                        </p>
                                    </div>
                                    <TrendingDown className="size-6 text-muted-foreground" />
                                </CardContent>
                            </Card>
                            <Card className="mt-3 overflow-hidden py-0">
                                <CardContent className="p-0">
                                    <div className="border-b px-5 py-4">
                                        <h3 className="font-semibold">
                                            Outstanding fee aging
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            Balances on invoices issued in the
                                            selected period, aged as at{' '}
                                            {filters.date_to}.
                                        </p>
                                    </div>
                                    <div className="grid sm:grid-cols-4">
                                        {[
                                            [
                                                'Not due',
                                                operational.aging.not_due,
                                            ],
                                            [
                                                '1–30 days',
                                                operational.aging.days_1_30,
                                            ],
                                            [
                                                '31–60 days',
                                                operational.aging.days_31_60,
                                            ],
                                            [
                                                '61+ days',
                                                operational.aging.days_61_plus,
                                            ],
                                        ].map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="border-b p-4 last:border-0 sm:border-r sm:border-b-0"
                                            >
                                                <p className="text-xs text-muted-foreground">
                                                    {label}
                                                </p>
                                                <p className="mt-1 font-semibold">
                                                    {formatCurrency(
                                                        operational.currency,
                                                        value,
                                                    )}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </section>

                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                <div className="border-b px-5 py-4">
                                    <h2 className="font-semibold">
                                        Branch comparison
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Academy-wide costs are not assigned to
                                        individual branches.
                                    </p>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[650px] text-left text-sm">
                                        <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Branch
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Active{' '}
                                                    {terminology.pluralLower}
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Collected
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Expenses
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {operational.branch_metrics.map(
                                                (branch) => (
                                                    <tr
                                                        key={branch.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="px-4 py-3 font-medium">
                                                            {branch.name}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            {
                                                                branch.active_players
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            {formatCurrency(
                                                                operational.currency,
                                                                branch.collected,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            {formatCurrency(
                                                                operational.currency,
                                                                branch.expenses,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </main>
        </>
    );
}
