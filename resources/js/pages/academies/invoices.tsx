import { Form, Head, Link } from '@inertiajs/react';
import {
    Banknote,
    ChevronDown,
    CircleDollarSign,
    Download,
    ReceiptText,
    Scale,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/money';

type Option = { id: number; name: string };
type Invoice = {
    id: number;
    number: string;
    fee_name: string;
    fee_type: string;
    status: string;
    issued_on: string;
    currency: string;
    billed: string;
    paid: string;
    owed: string;
    written_off: string;
    payer_name: string;
    player_id: number | null;
    application_id: number | null;
    application_reference: string | null;
    cycle_name: string;
    programme_name: string | null;
    branch_name: string | null;
};
type Paginator = {
    data: Invoice[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Summary = {
    currency: string;
    billed: string;
    paid: string;
    owed: string;
    written_off: string;
};
type Filters = {
    cycle_id: number | null;
    programme_id: number | null;
    branch_id: number | null;
    fee_name: string;
    status: string;
    date_from: string | null;
    date_to: string | null;
};

const fieldClass =
    'h-9 min-w-40 rounded-md border border-input bg-transparent px-3 text-sm';

function statusLabel(status: string) {
    return status === 'void' ? 'Cancelled' : status.replaceAll('_', ' ');
}

export default function Invoices({
    invoices,
    summary,
    cycles,
    programmes,
    branches,
    fee_names,
    filters,
}: {
    invoices: Paginator;
    summary: Summary;
    cycles: Option[];
    programmes: Option[];
    branches: Option[];
    fee_names: string[];
    filters: Filters;
}) {
    const [filtersOpen, setFiltersOpen] = useState(false);
    const activeFilterCount = [
        filters.cycle_id,
        filters.programme_id,
        filters.branch_id,
        filters.fee_name,
        filters.status,
        filters.date_from,
        filters.date_to,
    ].filter(Boolean).length;
    const summaryCards = [
        [
            'Total billed',
            summary.billed,
            ReceiptText,
            'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40',
            'text-blue-700 dark:text-blue-300',
        ],
        [
            'Total paid',
            summary.paid,
            Banknote,
            'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40',
            'text-emerald-700 dark:text-emerald-300',
        ],
        [
            'Total owed',
            summary.owed,
            Scale,
            'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40',
            'text-amber-800 dark:text-amber-200',
        ],
        [
            'Total written off',
            summary.written_off,
            CircleDollarSign,
            'border-violet-200 bg-violet-50 dark:border-violet-900 dark:bg-violet-950/40',
            'text-violet-700 dark:text-violet-300',
        ],
    ] as const;

    return (
        <>
            <Head title="Invoices" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy finance
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Invoices
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Review current and historical invoices across every
                            cycle. Summary totals always follow the filters
                            below.
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setFiltersOpen((open) => !open)}
                            aria-expanded={filtersOpen}
                            aria-controls="invoice-filters"
                        >
                            <SlidersHorizontal className="size-4" />
                            Filters
                            {activeFilterCount > 0 && (
                                <Badge
                                    variant="secondary"
                                    className="min-w-5 justify-center px-1.5"
                                >
                                    {activeFilterCount}
                                </Badge>
                            )}
                            <ChevronDown
                                className={`size-4 transition-transform ${filtersOpen ? 'rotate-180' : ''}`}
                            />
                        </Button>
                        <Button asChild variant="outline">
                            <a href="/app/reports/invoices.csv">
                                <Download className="size-4" />
                                Export CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <Collapsible
                    open={filtersOpen}
                    onOpenChange={setFiltersOpen}
                    className="mb-6"
                >
                    <CollapsibleTrigger className="sr-only">
                        Toggle invoice filters
                    </CollapsibleTrigger>
                    <CollapsibleContent id="invoice-filters">
                        <Form
                            action="/app/finance/invoices"
                            method="get"
                            className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            {() => (
                                <>
                                    <div className="grid gap-1">
                                        <Label htmlFor="invoice-cycle">
                                            Cycle
                                        </Label>
                                        <select
                                            id="invoice-cycle"
                                            name="cycle_id"
                                            className={fieldClass}
                                            defaultValue={
                                                filters.cycle_id ?? ''
                                            }
                                        >
                                            <option value="">All cycles</option>
                                            {cycles.map((cycle) => (
                                                <option
                                                    key={cycle.id}
                                                    value={cycle.id}
                                                >
                                                    {cycle.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="invoice-programme">
                                            Programme
                                        </Label>
                                        <select
                                            id="invoice-programme"
                                            name="programme_id"
                                            className={fieldClass}
                                            defaultValue={
                                                filters.programme_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                All programmes
                                            </option>
                                            {programmes.map((programme) => (
                                                <option
                                                    key={programme.id}
                                                    value={programme.id}
                                                >
                                                    {programme.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="invoice-branch">
                                            Branch
                                        </Label>
                                        <select
                                            id="invoice-branch"
                                            name="branch_id"
                                            className={fieldClass}
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
                                        <Label htmlFor="invoice-fee">
                                            Fee name
                                        </Label>
                                        <select
                                            id="invoice-fee"
                                            name="fee_name"
                                            className={fieldClass}
                                            defaultValue={filters.fee_name}
                                        >
                                            <option value="">
                                                All fee names
                                            </option>
                                            {fee_names.map((name) => (
                                                <option key={name} value={name}>
                                                    {name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="invoice-status">
                                            Status
                                        </Label>
                                        <select
                                            id="invoice-status"
                                            name="status"
                                            className={fieldClass}
                                            defaultValue={filters.status}
                                        >
                                            <option value="">
                                                All statuses
                                            </option>
                                            <option value="issued">
                                                Issued
                                            </option>
                                            <option value="partially_paid">
                                                Partially paid
                                            </option>
                                            <option value="partially_written_off">
                                                Partially written off
                                            </option>
                                            <option value="paid">Paid</option>
                                            <option value="written_off">
                                                Written off
                                            </option>
                                            <option value="void">
                                                Cancelled
                                            </option>
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="invoice-date-from">
                                            Issued from
                                        </Label>
                                        <Input
                                            id="invoice-date-from"
                                            name="date_from"
                                            type="date"
                                            defaultValue={
                                                filters.date_from ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="invoice-date-to">
                                            Issued to
                                        </Label>
                                        <Input
                                            id="invoice-date-to"
                                            name="date_to"
                                            type="date"
                                            defaultValue={filters.date_to ?? ''}
                                        />
                                    </div>
                                    <div className="flex items-end gap-2">
                                        <Button type="submit">
                                            Apply filters
                                        </Button>
                                        <Button
                                            asChild
                                            type="button"
                                            variant="outline"
                                        >
                                            <Link href="/app/finance/invoices">
                                                Reset
                                            </Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CollapsibleContent>
                </Collapsible>

                <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map(
                        ([label, amount, Icon, cardClass, accentClass]) => (
                            <Card key={label} className={cardClass}>
                                <CardContent className="pt-6">
                                    <Icon className={`size-5 ${accentClass}`} />
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p
                                        className={`mt-1 text-2xl font-semibold ${accentClass}`}
                                    >
                                        {formatCurrency(
                                            summary.currency,
                                            amount,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        ),
                    )}
                </div>

                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {invoices.data.length === 0 ? (
                            <div className="py-14 text-center">
                                <ReceiptText className="mx-auto size-9 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No invoices found
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Adjust the filters or issue an invoice to a
                                    player.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1350px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Issued
                                            </th>
                                            <th className="px-4 py-3">
                                                Invoice
                                            </th>
                                            <th className="px-4 py-3">Payer</th>
                                            <th className="px-4 py-3">
                                                Fee name
                                            </th>
                                            <th className="px-4 py-3">
                                                Programme
                                            </th>
                                            <th className="px-4 py-3">
                                                Branch
                                            </th>
                                            <th className="px-4 py-3">Cycle</th>
                                            <th className="px-4 py-3 text-right">
                                                Billed
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Paid
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Owed
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Written off
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invoices.data.map((invoice) => (
                                            <tr
                                                key={invoice.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    {invoice.issued_on}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {invoice.number}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {invoice.player_id ||
                                                    invoice.application_id ? (
                                                        <Link
                                                            href={
                                                                invoice.player_id
                                                                    ? `/app/players/${invoice.player_id}`
                                                                    : `/app/applications/${invoice.application_id}`
                                                            }
                                                            className="font-medium text-primary hover:underline"
                                                        >
                                                            {invoice.payer_name}
                                                        </Link>
                                                    ) : (
                                                        invoice.payer_name
                                                    )}
                                                    {invoice.application_reference && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                invoice.application_reference
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {invoice.fee_name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground capitalize">
                                                        {invoice.fee_type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}{' '}
                                                        fee
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {invoice.programme_name ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {invoice.branch_name ??
                                                        'All branches'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {invoice.cycle_name}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {formatCurrency(
                                                        invoice.currency,
                                                        invoice.billed,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right text-emerald-700 dark:text-emerald-300">
                                                    {formatCurrency(
                                                        invoice.currency,
                                                        invoice.paid,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium text-amber-800 dark:text-amber-200">
                                                    {formatCurrency(
                                                        invoice.currency,
                                                        invoice.owed,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {formatCurrency(
                                                        invoice.currency,
                                                        invoice.written_off,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            invoice.status ===
                                                            'void'
                                                                ? 'destructive'
                                                                : invoice.status ===
                                                                    'paid'
                                                                  ? 'secondary'
                                                                  : 'outline'
                                                        }
                                                        className="capitalize"
                                                    >
                                                        {statusLabel(
                                                            invoice.status,
                                                        )}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {invoices.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                                <p>
                                    Showing {invoices.from}–{invoices.to} of{' '}
                                    {invoices.total}
                                </p>
                                <div className="flex gap-2">
                                    {invoices.prev_page_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={invoices.prev_page_url}>
                                                Previous
                                            </Link>
                                        </Button>
                                    )}
                                    {invoices.next_page_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={invoices.next_page_url}>
                                                Next
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
