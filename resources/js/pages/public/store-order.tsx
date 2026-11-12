import {
    Building2,
    CheckCircle2,
    CircleAlert,
    CreditCard,
    LoaderCircle,
    PackageCheck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/money';
import { loadPaystack, openPaystack } from '@/lib/paystack';
import type { PublicAcademy, PublicSite } from '@/types/public-site';

type PaymentStatus = 'idle' | 'initializing' | 'waiting' | 'verified' | 'error';

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function jsonPost(url: string, body: Record<string, string> = {}) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = (await response.json().catch(() => ({}))) as Record<
        string,
        string
    >;

    return { response, payload };
}

export default function StoreOrder({
    academy,
    site,
    base_url,
    order_token,
    order,
    payment_preference,
    paystack_ready,
    bank_details,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    order_token: string;
    order: {
        number: string;
        customer_name: string;
        customer_email: string;
        member_name: string | null;
        currency: string;
        total: string;
        paid: string;
        outstanding: string;
        processing_fee: string;
        online_total: string;
        payment_status: string;
        fulfillment_status: string;
        delivery_method: string;
        branch: string | null;
        items: Array<{
            name: string;
            variant: string;
            quantity: number;
            unit_price: string;
        }>;
    };
    payment_preference: 'paystack' | 'bank_transfer' | 'pay_later';
    paystack_ready: boolean;
    bank_details: {
        bank_name: string | null;
        account_name: string | null;
        account_number: string | null;
    };
}) {
    const [paymentStatus, setPaymentStatus] = useState<PaymentStatus>('idle');
    const [message, setMessage] = useState('');
    const [paymentEmail, setPaymentEmail] = useState(order.customer_email);
    const isPaid = order.payment_status === 'paid';

    const pay = async () => {
        if (paymentStatus === 'initializing' || paymentStatus === 'waiting')
            return;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(paymentEmail.trim())) {
            const error =
                'Enter a valid payment email address before opening Paystack.';
            setPaymentStatus('error');
            setMessage(error);
            toast.error(error);
            return;
        }
        setPaymentStatus('initializing');
        setMessage('');
        try {
            await loadPaystack();
            const initialized = await jsonPost(
                `${base_url}/store/orders/${order_token}/payments`,
                { email: paymentEmail.trim() },
            );
            if (
                !initialized.response.ok ||
                !initialized.payload.access_code ||
                !initialized.payload.verify_url
            ) {
                throw new Error(
                    initialized.payload.message ??
                        'Payment could not be initialized.',
                );
            }
            setPaymentStatus('waiting');
            const outcome = await openPaystack(initialized.payload.access_code);
            if (outcome === 'closed') {
                setPaymentStatus('idle');
                toast.info(
                    'The Paystack window was closed before payment, so nothing was charged.',
                );
                return;
            }
            for (let attempt = 0; attempt < 40; attempt += 1) {
                const verified = await jsonPost(initialized.payload.verify_url);
                if (
                    verified.response.ok &&
                    verified.payload.status === 'verified'
                ) {
                    setPaymentStatus('verified');
                    setMessage(
                        verified.payload.message ??
                            'Payment verified. Your receipt has been emailed.',
                    );
                    window.location.assign(
                        `${base_url}/store/orders/${order_token}`,
                    );
                    return;
                }
                if (verified.payload.status === 'review_required') {
                    throw new Error(
                        verified.payload.message ??
                            'Payment requires academy review.',
                    );
                }
                await new Promise((resolve) =>
                    window.setTimeout(resolve, 3000),
                );
            }
            throw new Error(
                'Payment is still being confirmed. Refresh this page shortly.',
            );
        } catch (error) {
            const errorMessage =
                error instanceof Error
                    ? error.message
                    : 'Payment could not be completed.';
            setPaymentStatus('error');
            setMessage(errorMessage);
            toast.error(errorMessage);
        }
    };

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`Order ${order.number} · ${academy.name}`}
        >
            <main className="mx-auto min-h-[70vh] max-w-3xl px-5 py-16 sm:px-8">
                <div className="rounded-3xl border bg-white p-7 shadow-sm sm:p-10">
                    <CheckCircle2 className="size-12 text-emerald-600" />
                    <p className="mt-5 text-sm font-bold text-[var(--site-primary)]">
                        ORDER {order.number}
                    </p>
                    <h1 className="mt-2 text-3xl font-black">
                        {isPaid || payment_preference !== 'paystack'
                            ? 'Order successfully placed'
                            : 'Your order is reserved'}
                    </h1>
                    <p className="mt-3 text-slate-600">
                        Thank you, {order.customer_name}. Your order is now
                        visible to the academy.{' '}
                        {isPaid
                            ? 'Your payment has also been confirmed.'
                            : payment_preference === 'paystack'
                              ? 'Complete the Paystack payment below to finish checkout.'
                              : 'The academy will follow up on the outstanding payment.'}
                    </p>
                    {order.member_name && (
                        <p className="mt-3 rounded-xl bg-slate-50 p-3 text-sm">
                            For {academy.member_singular.toLowerCase()}:{' '}
                            <strong>{order.member_name}</strong>
                        </p>
                    )}
                    <div className="mt-8 divide-y rounded-2xl border">
                        {order.items.map((item, index) => (
                            <div
                                key={`${item.name}-${index}`}
                                className="flex justify-between gap-4 p-4"
                            >
                                <div>
                                    <strong>{item.name}</strong>
                                    <p className="text-sm text-slate-500">
                                        {item.variant} · Qty {item.quantity}
                                    </p>
                                </div>
                                <span>
                                    {formatCurrency(
                                        order.currency,
                                        (
                                            Number(item.unit_price) *
                                            item.quantity
                                        ).toFixed(2),
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-5 sm:grid-cols-3">
                        <div>
                            <p className="text-xs text-slate-500 uppercase">
                                Total
                            </p>
                            <strong>
                                {formatCurrency(order.currency, order.total)}
                            </strong>
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 uppercase">
                                Payment
                            </p>
                            <strong className="capitalize">
                                {order.payment_status.replace('_', ' ')}
                            </strong>
                        </div>
                        <div>
                            <p className="text-xs text-slate-500 uppercase">
                                Delivery
                            </p>
                            <strong className="capitalize">
                                {order.fulfillment_status}
                            </strong>
                        </div>
                    </div>

                    {!isPaid && (
                        <section className="mt-8 space-y-4 border-t pt-8">
                            <div>
                                <h2 className="text-xl font-black">
                                    Complete payment
                                </h2>
                                <p className="text-sm text-slate-600">
                                    Outstanding:{' '}
                                    <strong>
                                        {formatCurrency(
                                            order.currency,
                                            order.outstanding,
                                        )}
                                    </strong>
                                </p>
                            </div>
                            {paystack_ready && (
                                <div
                                    className={`rounded-2xl border p-5 ${payment_preference === 'paystack' ? 'ring-2 ring-[var(--site-primary)]' : ''}`}
                                >
                                    <div className="flex items-start gap-3">
                                        <CreditCard className="mt-1 size-5 text-[var(--site-primary)]" />
                                        <div className="flex-1">
                                            <h3 className="font-bold">
                                                Pay online with Paystack
                                            </h3>
                                            <p className="mt-1 text-sm text-slate-600">
                                                Online payment fee:{' '}
                                                {formatCurrency(
                                                    order.currency,
                                                    order.processing_fee,
                                                )}{' '}
                                                · Total charge:{' '}
                                                <strong>
                                                    {formatCurrency(
                                                        order.currency,
                                                        order.online_total,
                                                    )}
                                                </strong>
                                            </p>
                                            <div className="mt-4 max-w-sm">
                                                <Label htmlFor="store-payment-email">
                                                    Payment and receipt email
                                                </Label>
                                                <Input
                                                    id="store-payment-email"
                                                    type="email"
                                                    required
                                                    value={paymentEmail}
                                                    onChange={(event) =>
                                                        setPaymentEmail(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <p className="mt-1 text-xs text-slate-500">
                                                    You can correct the email
                                                    here before opening
                                                    Paystack.
                                                </p>
                                            </div>
                                            {message && (
                                                <p
                                                    role="alert"
                                                    className={`mt-4 flex items-start gap-2 rounded-xl border p-4 text-sm font-medium ${paymentStatus === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
                                                >
                                                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                                    {message}
                                                </p>
                                            )}
                                            <Button
                                                className="mt-4 bg-[var(--site-primary)] text-[var(--site-primary-fg)]"
                                                disabled={
                                                    paymentStatus ===
                                                        'initializing' ||
                                                    paymentStatus === 'waiting'
                                                }
                                                onClick={pay}
                                            >
                                                {paymentStatus ===
                                                    'initializing' ||
                                                paymentStatus === 'waiting' ? (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                ) : (
                                                    <CreditCard className="size-4" />
                                                )}{' '}
                                                {paymentStatus === 'waiting'
                                                    ? 'Confirming payment…'
                                                    : 'Pay with Paystack'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            )}
                            {bank_details.account_number && (
                                <div
                                    className={`rounded-2xl border p-5 ${payment_preference === 'bank_transfer' ? 'ring-2 ring-[var(--site-primary)]' : ''}`}
                                >
                                    <div className="flex items-start gap-3">
                                        <Building2 className="mt-1 size-5 text-[var(--site-primary)]" />
                                        <div>
                                            <h3 className="font-bold">
                                                Bank transfer
                                            </h3>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {bank_details.bank_name} ·{' '}
                                                {bank_details.account_name}
                                            </p>
                                            <p className="mt-2 text-xl font-black tracking-wide">
                                                {bank_details.account_number}
                                            </p>
                                            <p className="mt-2 text-xs text-slate-500">
                                                Use {order.number} as your
                                                transfer reference. The academy
                                                will verify the transfer and
                                                update this order.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                            {payment_preference === 'pay_later' && (
                                <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                                    This order has been placed without payment.
                                    You can return to this private order link to
                                    pay later.
                                </p>
                            )}
                        </section>
                    )}

                    <a
                        href={`${base_url}/store`}
                        className="mt-8 inline-flex items-center gap-2 font-bold text-[var(--site-primary)]"
                    >
                        <PackageCheck className="size-4" /> Continue shopping
                    </a>
                </div>
            </main>
        </PublicSiteShell>
    );
}
