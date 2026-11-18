import { Head, Link, router } from '@inertiajs/react';
import {
    Banknote,
    CalendarCheck,
    Hourglass,
    Percent,
    Search,
    TrendingUp,
    UserX,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    formatDate,
    minorToMoney,
    paymentStatusLabels,
} from '@/types/subscription';

type Row = {
    id: number;
    reference: string;
    academy: { id: number; name: string };
    plan_name: string;
    billing_interval_label: string;
    currency: string;
    amount_minor: number;
    surcharge_minor: number;
    platform_fee_minor: number;
    charged_amount_minor: number;
    status: string;
    created_at: string | null;
    verified_at: string | null;
    period_starts_at: string | null;
    period_ends_at: string | null;
};

type Props = {
    summary: {
        currency: string;
        revenue_minor: number;
        revenue_this_month_minor: number;
        platform_fees_minor: number;
        payments_count: number;
        paying_academies: number;
        trialing_academies: number;
        lapsed_academies: number;
    };
    payments: {
        data: Row[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { status: string; search: string };
};

const statusOptions = [
    ['verified', 'Paid'],
    ['pending', 'Pending'],
    ['failed', 'Failed'],
    ['expired', 'Expired'],
    ['review_required', 'Needs review'],
    ['all', 'All'],
] as const;

function StatCard({
    icon,
    label,
    value,
    hint,
}: {
    icon: ReactNode;
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                    {icon}
                    {label}
                </p>
                <p className="mt-2 text-xl font-semibold tracking-tight">
                    {value}
                </p>
                {hint && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {hint}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function TenantSubscriptions({
    summary,
    payments,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const money = (minor: number) => minorToMoney(summary.currency, minor);

    function filter(next: Partial<Props['filters']>) {
        router.get(
            '/platform/subscriptions',
            { ...filters, search, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        filter({ search });
    }

    return (
        <>
            <Head title="Tenant subscriptions" />
            <main className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <p className="text-sm font-medium text-primary">Platform</p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Tenant subscriptions
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Subscription payments from academies. Revenue is the
                        plan price that settles to your account. The online
                        payment fee is shown separately.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        icon={<Banknote className="size-4" />}
                        label="Total revenue"
                        value={money(summary.revenue_minor)}
                        hint={`${summary.payments_count} paid ${summary.payments_count === 1 ? 'payment' : 'payments'}`}
                    />
                    <StatCard
                        icon={<TrendingUp className="size-4" />}
                        label="This month"
                        value={money(summary.revenue_this_month_minor)}
                    />
                    <StatCard
                        icon={<Percent className="size-4" />}
                        label="Platform fees"
                        value={money(summary.platform_fees_minor)}
                        hint="Your fee on top of plan prices"
                    />
                    <StatCard
                        icon={<CalendarCheck className="size-4" />}
                        label="Paying academies"
                        value={String(summary.paying_academies)}
                        hint={`${summary.trialing_academies} on trial · ${summary.lapsed_academies} expired`}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div
                        role="radiogroup"
                        aria-label="Payment status"
                        className="inline-flex flex-wrap rounded-lg border bg-muted/40 p-1"
                    >
                        {statusOptions.map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={filters.status === value}
                                onClick={() => filter({ status: value })}
                                className={`rounded-md px-3 py-1.5 text-sm font-medium transition ${filters.status === value ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <form
                        onSubmit={submitSearch}
                        className="flex flex-1 items-center gap-2 sm:max-w-xs"
                    >
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Academy or reference"
                            aria-label="Search subscription payments"
                        />
                        <Button type="submit" variant="outline" size="icon">
                            <Search className="size-4" />
                            <span className="sr-only">Search</span>
                        </Button>
                    </form>
                </div>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        {payments.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                                {filters.status === 'verified' ? (
                                    <Hourglass className="size-6" />
                                ) : (
                                    <UserX className="size-6" />
                                )}
                                No subscription payments match this view.
                            </div>
                        ) : (
                            <table className="w-full min-w-[820px] text-left text-sm">
                                <thead className="border-b text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Academy
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Plan
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Period
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Plan price
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Fee
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Paid
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {payments.data.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/platform/academies/${row.academy.id}/subscription`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.academy.name}
                                                </Link>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {row.reference}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.plan_name}
                                                <p className="text-xs text-muted-foreground">
                                                    {row.billing_interval_label}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.period_starts_at
                                                    ? `${formatDate(row.period_starts_at)} – ${formatDate(row.period_ends_at)}`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {minorToMoney(
                                                    row.currency,
                                                    row.amount_minor,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground">
                                                {minorToMoney(
                                                    row.currency,
                                                    row.surcharge_minor,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatDate(
                                                    row.verified_at ??
                                                        row.created_at,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        row.status ===
                                                        'verified'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {paymentStatusLabels[
                                                        row.status
                                                    ] ?? row.status}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                {payments.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between"
                        aria-label="Subscription payment pages"
                    >
                        <Button
                            variant="outline"
                            disabled={!payments.prev_page_url}
                            asChild={Boolean(payments.prev_page_url)}
                        >
                            {payments.prev_page_url ? (
                                <Link href={payments.prev_page_url}>
                                    Previous
                                </Link>
                            ) : (
                                <span>Previous</span>
                            )}
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {payments.current_page} of {payments.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={!payments.next_page_url}
                            asChild={Boolean(payments.next_page_url)}
                        >
                            {payments.next_page_url ? (
                                <Link href={payments.next_page_url}>Next</Link>
                            ) : (
                                <span>Next</span>
                            )}
                        </Button>
                    </nav>
                )}
            </main>
        </>
    );
}
