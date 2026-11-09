import { Head } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { formatCurrency } from '@/lib/money';

type Allocation = { branch: string; quantity: number };
type OrderItem = {
    id: number;
    name: string;
    variant: string | null;
    sku: string | null;
    unit_price: string;
    quantity: number;
    line_total: string;
    allocations: Allocation[];
};
type OrderPayment = {
    id: number;
    reference: string;
    external_reference: string | null;
    method: string;
    status: string;
    amount: string;
    surcharge: string;
    charged_amount: string;
    provider_fee: string;
    received_on: string | null;
    verified_at: string | null;
    created_at: string | null;
    failure_reason: string | null;
    notes: string | null;
    recorded_by: string | null;
};
type Order = {
    id: number;
    number: string;
    source: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    member_name: string | null;
    collection_branch: string | null;
    delivery_method: string;
    delivery_address: string | null;
    notes: string | null;
    currency: string;
    subtotal: string;
    discount: string;
    delivery: string;
    total: string;
    paid: string;
    outstanding: string;
    payment_status: string;
    payment_preference: string | null;
    fulfillment_status: string;
    placed_at: string | null;
    paid_at: string | null;
    fulfilled_at: string | null;
    cancelled_at: string | null;
    items: OrderItem[];
    payments: OrderPayment[];
};

const label = (value: string | null) =>
    value
        ? value.replaceAll('_', ' ').replace(/\b\w/g, (x) => x.toUpperCase())
        : 'Not set';

const date = (value: string | null, withTime = false) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              ...(withTime ? { timeStyle: 'short' as const } : {}),
          }).format(new Date(value))
        : '—';

const statusClass = (value: string) => {
    if (['paid', 'verified', 'delivered', 'fulfilled'].includes(value)) {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (['cancelled', 'failed', 'reversed'].includes(value)) {
        return 'bg-red-100 text-red-700';
    }
    if (['partially_paid', 'processing', 'ready', 'shipped'].includes(value)) {
        return 'bg-blue-100 text-blue-800';
    }
    return 'bg-amber-100 text-amber-800';
};

const StatusBadge = ({ value }: { value: string }) => (
    <span
        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${statusClass(value)}`}
    >
        {label(value)}
    </span>
);

export default function StoreOrderDetails({
    order_academy,
    order,
}: {
    order_academy: { name: string; currency: string };
    order: Order;
}) {
    const money = (value: string) => formatCurrency(order.currency, value);

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <main className="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
                <div
                    data-print-hide
                    className="flex flex-wrap items-center justify-between gap-3"
                >
                    <Button variant="ghost" asChild>
                        <a href="/app/store?tab=orders">
                            <ArrowLeft className="size-4" /> Back to orders
                        </a>
                    </Button>
                    <Button onClick={() => window.print()}>
                        <Printer className="size-4" /> Print order
                    </Button>
                </div>

                <article
                    data-print-document
                    className="space-y-6 rounded-xl print:rounded-none"
                >
                    <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                        <div>
                            <p className="text-sm font-semibold text-primary">
                                {order_academy.name}
                            </p>
                            <h1 className="mt-1 text-2xl font-bold sm:text-3xl">
                                Order {order.number}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Placed {date(order.placed_at, true)} ·{' '}
                                {label(order.source)} order
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <StatusBadge value={order.payment_status} />
                            <StatusBadge value={order.fulfillment_status} />
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-3">
                        <Card className="border-blue-200 bg-blue-50/70">
                            <CardContent className="p-5">
                                <p className="text-sm text-blue-800">
                                    Order total
                                </p>
                                <p className="mt-1 text-2xl font-bold text-blue-950">
                                    {money(order.total)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="border-emerald-200 bg-emerald-50/70">
                            <CardContent className="p-5">
                                <p className="text-sm text-emerald-800">
                                    Verified payments
                                </p>
                                <p className="mt-1 text-2xl font-bold text-emerald-950">
                                    {money(order.paid)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="border-amber-200 bg-amber-50/70">
                            <CardContent className="p-5">
                                <p className="text-sm text-amber-800">
                                    Outstanding
                                </p>
                                <p className="mt-1 text-2xl font-bold text-amber-950">
                                    {money(order.outstanding)}
                                </p>
                            </CardContent>
                        </Card>
                    </section>

                    <section className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <h2 className="text-lg font-bold">
                                    Customer details
                                </h2>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                                <Detail
                                    title="Customer"
                                    value={order.customer_name}
                                />
                                <Detail
                                    title="Student / player"
                                    value={order.member_name ?? '—'}
                                />
                                <Detail
                                    title="Email"
                                    value={order.customer_email ?? '—'}
                                />
                                <Detail
                                    title="Phone"
                                    value={order.customer_phone ?? '—'}
                                />
                                <Detail
                                    title="Preferred collection location"
                                    value={
                                        order.collection_branch ??
                                        'Not selected'
                                    }
                                />
                                <Detail
                                    title="Delivery method"
                                    value={label(order.delivery_method)}
                                />
                                {order.delivery_address && (
                                    <div className="sm:col-span-2">
                                        <Detail
                                            title="Delivery address"
                                            value={order.delivery_address}
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <h2 className="text-lg font-bold">
                                    Order status
                                </h2>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                                <Detail
                                    title="Payment status"
                                    value={label(order.payment_status)}
                                />
                                <Detail
                                    title="Payment preference"
                                    value={label(order.payment_preference)}
                                />
                                <Detail
                                    title="Fulfilment status"
                                    value={label(order.fulfillment_status)}
                                />
                                <Detail
                                    title="Paid at"
                                    value={date(order.paid_at, true)}
                                />
                                <Detail
                                    title="Fulfilled at"
                                    value={date(order.fulfilled_at, true)}
                                />
                                <Detail
                                    title="Cancelled at"
                                    value={date(order.cancelled_at, true)}
                                />
                                {order.notes && (
                                    <div className="sm:col-span-2">
                                        <Detail
                                            title="Order notes"
                                            value={order.notes}
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>

                    <Card>
                        <CardHeader>
                            <h2 className="text-lg font-bold">Items</h2>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full min-w-[680px] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-4">Item</th>
                                        <th className="p-4">
                                            Stock allocation
                                        </th>
                                        <th className="p-4 text-right">
                                            Unit price
                                        </th>
                                        <th className="p-4 text-right">Qty</th>
                                        <th className="p-4 text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-t align-top"
                                        >
                                            <td className="p-4 font-semibold">
                                                {item.name}
                                                <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                    {[item.variant, item.sku]
                                                        .filter(Boolean)
                                                        .join(' · ') ||
                                                        'Standard item'}
                                                </p>
                                            </td>
                                            <td className="p-4 text-muted-foreground">
                                                {item.allocations.length > 0
                                                    ? item.allocations
                                                          .map(
                                                              (allocation) =>
                                                                  `${allocation.branch} (${allocation.quantity})`,
                                                          )
                                                          .join(', ')
                                                    : 'Not inventory tracked'}
                                            </td>
                                            <td className="p-4 text-right">
                                                {money(item.unit_price)}
                                            </td>
                                            <td className="p-4 text-right">
                                                {item.quantity}
                                            </td>
                                            <td className="p-4 text-right font-semibold">
                                                {money(item.line_total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t bg-muted/20">
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-2 text-right"
                                        >
                                            Subtotal
                                        </td>
                                        <td className="p-2 text-right">
                                            {money(order.subtotal)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-2 text-right"
                                        >
                                            Discount
                                        </td>
                                        <td className="p-2 text-right">
                                            {money(order.discount)}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-2 text-right"
                                        >
                                            Delivery
                                        </td>
                                        <td className="p-2 text-right">
                                            {money(order.delivery)}
                                        </td>
                                    </tr>
                                    <tr className="text-base font-bold">
                                        <td
                                            colSpan={4}
                                            className="p-3 text-right"
                                        >
                                            Order total
                                        </td>
                                        <td className="p-3 text-right">
                                            {money(order.total)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h2 className="text-lg font-bold">
                                Payment history
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Only verified payments reduce the outstanding
                                balance.
                            </p>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            {order.payments.length > 0 ? (
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="p-4">
                                                Date / reference
                                            </th>
                                            <th className="p-4">Method</th>
                                            <th className="p-4">Status</th>
                                            <th className="p-4 text-right">
                                                Applied to order
                                            </th>
                                            <th className="p-4 text-right">
                                                Customer fee
                                            </th>
                                            <th className="p-4 text-right">
                                                Charged
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.payments.map((payment) => (
                                            <tr
                                                key={payment.id}
                                                className="border-t align-top"
                                            >
                                                <td className="p-4">
                                                    {date(
                                                        payment.received_on ??
                                                            payment.created_at,
                                                    )}
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {payment.external_reference ??
                                                            payment.reference}
                                                    </p>
                                                    {payment.recorded_by && (
                                                        <p className="text-xs text-muted-foreground">
                                                            Recorded by{' '}
                                                            {
                                                                payment.recorded_by
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="p-4">
                                                    {label(payment.method)}
                                                </td>
                                                <td className="p-4">
                                                    <StatusBadge
                                                        value={payment.status}
                                                    />
                                                    {payment.failure_reason && (
                                                        <p className="mt-2 max-w-xs text-xs text-red-700">
                                                            {
                                                                payment.failure_reason
                                                            }
                                                        </p>
                                                    )}
                                                    {payment.notes && (
                                                        <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                            {payment.notes}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right font-semibold">
                                                    {money(payment.amount)}
                                                </td>
                                                <td className="p-4 text-right">
                                                    {money(payment.surcharge)}
                                                </td>
                                                <td className="p-4 text-right">
                                                    {money(
                                                        payment.charged_amount,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="p-8 text-center text-muted-foreground">
                                    No payment attempts or receipts have been
                                    recorded for this order.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </article>
            </main>
        </>
    );
}

function Detail({ title, value }: { title: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </p>
            <p className="mt-1 font-medium whitespace-pre-wrap">{value}</p>
        </div>
    );
}
