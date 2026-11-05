import { Form, Head, Link } from '@inertiajs/react';
import { Banknote, Download, ReceiptText, RotateCcw } from 'lucide-react';
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
import { formatCurrency, formatMoneyInput } from '@/lib/money';

type Option = { id: number; name: string };
type Payment = {
    id: number;
    reference: string;
    external_reference: string | null;
    received_on: string;
    currency: string;
    amount: string;
    reversed: string;
    net: string;
    adjustments_count: number;
    method: string;
    status: string;
    payer_name: string;
    application_id: number | null;
    application_reference: string | null;
    player_id: number | null;
    invoice_number: string | null;
    payment_type: string | null;
    cycle_name: string | null;
    programme_name: string | null;
};
type Paginator = {
    data: Payment[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const selectClass =
    'h-9 min-w-44 rounded-md border border-input bg-transparent px-3 text-sm';

function ReversalDialog({
    payment,
    today,
}: {
    payment: Payment;
    today: string;
}) {
    const [open, setOpen] = useState(false);
    const [amount, setAmount] = useState(payment.net);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    disabled={Number(payment.net) <= 0}
                >
                    <RotateCcw className="size-4" /> Reverse
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reverse payment</DialogTitle>
                    <DialogDescription>
                        Record a partial or full reversal. The original payment
                        remains in the audit trail and invoice balances reopen
                        automatically.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/finance/payments/${payment.id}/reversals`}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`reversal-amount-${payment.id}`}
                                >
                                    Amount
                                </Label>
                                <Input
                                    id={`reversal-amount-${payment.id}`}
                                    name="amount"
                                    value={amount}
                                    onChange={(event) =>
                                        setAmount(
                                            formatMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="decimal"
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`reversal-date-${payment.id}`}>
                                    Effective date
                                </Label>
                                <Input
                                    id={`reversal-date-${payment.id}`}
                                    name="effective_on"
                                    type="date"
                                    defaultValue={today}
                                    max={today}
                                    required
                                />
                                <InputError message={errors.effective_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`reversal-reason-${payment.id}`}
                                >
                                    Reason
                                </Label>
                                <textarea
                                    id={`reversal-reason-${payment.id}`}
                                    name="reason"
                                    className="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                                    maxLength={2000}
                                    required
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}Record reversal
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Payments({
    payments,
    cycles,
    programmes,
    filters,
    today,
}: {
    payments: Paginator;
    cycles: Option[];
    programmes: Option[];
    filters: {
        cycle_id: number | null;
        programme_id: number | null;
        method: string;
    };
    today: string;
}) {
    return (
        <>
            <Head title="Payments" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy finance
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Payments
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Trace every verified payment to its payer, fee type,
                            programme and cycle—even after an applicant becomes
                            a student.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href="/app/reports/payments.csv">
                            <Download className="size-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>

                <Form
                    action="/app/finance/payments"
                    method="get"
                    className="mb-5 flex flex-wrap items-end gap-3"
                >
                    {() => (
                        <>
                            <div className="grid gap-1">
                                <Label>Cycle</Label>
                                <select
                                    name="cycle_id"
                                    className={selectClass}
                                    defaultValue={filters.cycle_id ?? ''}
                                >
                                    <option value="">All cycles</option>
                                    {cycles.map((cycle) => (
                                        <option key={cycle.id} value={cycle.id}>
                                            {cycle.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Programme</Label>
                                <select
                                    name="programme_id"
                                    className={selectClass}
                                    defaultValue={filters.programme_id ?? ''}
                                >
                                    <option value="">All programmes</option>
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
                                <Label>Method</Label>
                                <select
                                    name="method"
                                    className={selectClass}
                                    defaultValue={filters.method}
                                >
                                    <option value="">All methods</option>
                                    <option value="bank_transfer">
                                        Bank transfer
                                    </option>
                                    <option value="cash">Cash</option>
                                    <option value="pos">POS</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </>
                    )}
                </Form>

                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {payments.data.length === 0 ? (
                            <div className="py-14 text-center">
                                <Banknote className="mx-auto size-9 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No payments found
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Verified application and programme payments
                                    will appear here.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1100px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Date</th>
                                            <th className="px-4 py-3">Payer</th>
                                            <th className="px-4 py-3">Type</th>
                                            <th className="px-4 py-3">
                                                Programme
                                            </th>
                                            <th className="px-4 py-3">Cycle</th>
                                            <th className="px-4 py-3">
                                                Method
                                            </th>
                                            <th className="px-4 py-3">
                                                Reference
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Amount
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payments.data.map((payment) => (
                                            <tr
                                                key={payment.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    {payment.received_on}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {payment.player_id ||
                                                    payment.application_id ? (
                                                        <Link
                                                            className="font-medium text-primary hover:underline"
                                                            href={
                                                                payment.player_id
                                                                    ? `/app/players/${payment.player_id}`
                                                                    : `/app/applications/${payment.application_id}`
                                                            }
                                                        >
                                                            {payment.payer_name}
                                                        </Link>
                                                    ) : (
                                                        <span className="font-medium">
                                                            {payment.payer_name}
                                                        </span>
                                                    )}
                                                    {payment.player_id &&
                                                        payment.application_id && (
                                                            <p className="text-xs text-muted-foreground">
                                                                Student · from{' '}
                                                                <Link
                                                                    className="hover:underline"
                                                                    href={`/app/applications/${payment.application_id}`}
                                                                >
                                                                    {payment.application_reference ??
                                                                        'application'}
                                                                </Link>
                                                            </p>
                                                        )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant="secondary">
                                                        {payment.payment_type?.replace(
                                                            '_',
                                                            ' ',
                                                        ) ?? 'Payment'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {payment.programme_name ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {payment.cycle_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {payment.method.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {payment.external_reference ??
                                                        payment.reference}
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium">
                                                    {formatCurrency(
                                                        payment.currency,
                                                        payment.net,
                                                    )}
                                                    {Number(payment.reversed) >
                                                        0 && (
                                                        <p className="text-xs text-red-600">
                                                            Reversed{' '}
                                                            {formatCurrency(
                                                                payment.currency,
                                                                payment.reversed,
                                                            )}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <a
                                                                href={`/app/finance/payments/${payment.id}/receipt`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                <ReceiptText className="size-4" />
                                                                Receipt
                                                            </a>
                                                        </Button>
                                                        <ReversalDialog
                                                            payment={payment}
                                                            today={today}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {payments.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                                <p>
                                    Showing {payments.from}–{payments.to} of{' '}
                                    {payments.total}
                                </p>
                                <div className="flex gap-2">
                                    {payments.prev_page_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={payments.prev_page_url}>
                                                Previous
                                            </Link>
                                        </Button>
                                    )}
                                    {payments.next_page_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={payments.next_page_url}>
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
