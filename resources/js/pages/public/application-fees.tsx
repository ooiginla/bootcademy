import { Form, Head } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    CreditCard,
    LoaderCircle,
    LockKeyhole,
    MailCheck,
    ReceiptText,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/money';
import type { PublicAcademy, PublicSite } from '@/types/public-site';

type PaymentOption = {
    key: string;
    label: string;
    currency: string;
    application_amount: string;
    programme_amount: string;
    programme_label?: string | null;
    payment_plan_name?: string | null;
    cycle_fee_amount?: string;
    cycle_fees?: Array<{ name: string; amount: string }>;
    base_amount: string;
    processing_fee: string;
    total_charge: string;
};
type PaymentStatus = 'idle' | 'initializing' | 'waiting' | 'verified' | 'error';

declare global {
    interface Window {
        PaystackPop?: new () => {
            resumeTransaction: (accessCode: string) => void;
        };
    }
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function jsonPost(url: string, body: Record<string, string>) {
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

function loadPaystack(): Promise<void> {
    if (window.PaystackPop) return Promise.resolve();

    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            'script[data-bootcademy-paystack]',
        );
        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener(
                'error',
                () => reject(new Error('Unable to load Paystack.')),
                { once: true },
            );
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://js.paystack.co/v2/inline.js';
        script.async = true;
        script.dataset.bootcademyPaystack = 'true';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Unable to load Paystack.'));
        document.head.appendChild(script);
    });
}

export default function PublicApplicationFees({
    academy,
    site,
    base_url,
    application,
    offering,
    payment_plans = [],
    cycle_fees = [],
    payment_options,
    paystack_ready,
    completion_url,
    submitted = false,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    application: {
        reference: string;
        name: string;
        email: string;
        token: string;
    };
    offering: {
        programme_name: string;
        cycle_name: string;
        branch_name: string;
        currency: string;
        fee_items: Array<{ name: string; amount_minor: number }>;
    };
    payment_plans?: Array<{
        id: number;
        name: string;
        description: string | null;
        is_selected: boolean;
        items: Array<{
            name: string;
            description: string | null;
            amount: string;
        }>;
        first_payment: {
            name: string;
            amount: string;
            due_on: string | null;
            due_offset_days: number | null;
        } | null;
        payment_count: number;
    }>;
    cycle_fees?: Array<{
        id: number;
        name: string;
        category: string;
        amount: string;
        is_compulsory: boolean;
        is_selected: boolean;
    }>;
    payment_options: PaymentOption[];
    paystack_ready: boolean;
    completion_url: string;
    submitted?: boolean;
}) {
    const [selection, setSelection] = useState(payment_options[0]?.key ?? '');
    const [selectedPlanId, setSelectedPlanId] = useState(
        String(
            payment_plans.find((plan) => plan.is_selected)?.id ??
                payment_plans[0]?.id ??
                '',
        ),
    );
    const [selectedCycleFeeIds, setSelectedCycleFeeIds] = useState<number[]>(
        cycle_fees.filter((fee) => fee.is_selected).map((fee) => fee.id),
    );
    const [submittedDialogOpen, setSubmittedDialogOpen] = useState(submitted);
    const [status, setStatus] = useState<PaymentStatus>('idle');
    const [message, setMessage] = useState('');
    const [paymentReference, setPaymentReference] = useState('');
    const stopped = useRef(false);
    const selected = useMemo(
        () => payment_options.find((option) => option.key === selection),
        [payment_options, selection],
    );

    useEffect(() => {
        setSelection(payment_options[0]?.key ?? '');
    }, [payment_options]);

    useEffect(() => {
        setSelectedPlanId(
            String(
                payment_plans.find((plan) => plan.is_selected)?.id ??
                    payment_plans[0]?.id ??
                    '',
            ),
        );
        setSelectedCycleFeeIds(
            cycle_fees.filter((fee) => fee.is_selected).map((fee) => fee.id),
        );
    }, [payment_plans, cycle_fees]);

    useEffect(
        () => () => {
            stopped.current = true;
        },
        [],
    );

    const pollVerification = async (
        verifyUrl: string,
        paymentCompletionUrl: string,
    ) => {
        setStatus('waiting');
        for (let attempt = 0; attempt < 40 && !stopped.current; attempt += 1) {
            const { response, payload } = await jsonPost(verifyUrl, {
                token: application.token,
            });
            if (response.ok && payload.status === 'verified') {
                setPaymentReference(payload.payment_reference ?? '');
                setMessage(
                    payload.message ??
                        'Payment verified. Your receipt has been emailed.',
                );
                setStatus('verified');
                window.location.assign(
                    payload.completion_url ?? paymentCompletionUrl,
                );
                return;
            }
            if (payload.status === 'failed' && payload.completion_url) {
                window.location.assign(payload.completion_url);
                return;
            }
            if (payload.status === 'review_required') {
                window.location.assign(
                    payload.completion_url ?? paymentCompletionUrl,
                );
                return;
            }
            if (response.status >= 400) {
                throw new Error(
                    payload.message ?? 'We could not verify this payment.',
                );
            }
            await new Promise((resolve) => window.setTimeout(resolve, 3000));
        }
        window.location.assign(paymentCompletionUrl);
    };

    const pay = async () => {
        if (!selected || status === 'initializing' || status === 'waiting')
            return;
        stopped.current = false;
        setStatus('initializing');
        setMessage('');
        let paymentCompletionUrl = '';
        try {
            await loadPaystack();
            const { response, payload } = await jsonPost(
                `${base_url}/applications/${application.reference}/payments`,
                {
                    token: application.token,
                    selection: selected.key,
                },
            );
            paymentCompletionUrl = payload.completion_url ?? '';
            if (!response.ok || !payload.access_code || !payload.verify_url) {
                throw new Error(
                    payload.message ?? 'Payment could not be initialized.',
                );
            }
            if (!window.PaystackPop)
                throw new Error(
                    'Paystack did not load correctly. Please retry.',
                );
            new window.PaystackPop().resumeTransaction(payload.access_code);
            await pollVerification(
                payload.verify_url,
                paymentCompletionUrl || completion_url,
            );
        } catch (error) {
            if (paymentCompletionUrl) {
                window.location.assign(paymentCompletionUrl);
                return;
            }
            setStatus('error');
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Payment could not be completed.',
            );
        }
    };

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`Fees · ${academy.name}`}
        >
            <Head title={`Application fees · ${academy.name}`} />
            <Dialog
                open={submittedDialogOpen}
                onOpenChange={setSubmittedDialogOpen}
            >
                <DialogContent className="overflow-hidden border-0 p-0 sm:max-w-md">
                    <div className="bg-emerald-600 px-6 py-8 text-center text-white">
                        <CheckCircle2 className="mx-auto size-14" />
                        <DialogHeader className="mt-4 text-center sm:text-center">
                            <DialogTitle className="text-2xl font-black">
                                Application submitted
                            </DialogTitle>
                            <DialogDescription className="text-emerald-50">
                                Your application has been saved successfully.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <div className="space-y-4 px-6 pb-6">
                        <p className="text-center text-sm leading-6 text-slate-600">
                            Reference{' '}
                            <strong className="text-slate-950">
                                {application.reference}
                            </strong>
                            . You can now review your payment plan and add any
                            optional cycle fees before paying.
                        </p>
                        <DialogFooter>
                            <DialogClose asChild>
                                <button
                                    type="button"
                                    className="w-full rounded-full bg-slate-950 px-5 py-3 text-sm font-black text-white"
                                >
                                    Review my fees
                                </button>
                            </DialogClose>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
            <main className="min-h-screen bg-slate-50">
                <section className="bg-slate-950 px-5 py-12 text-white sm:px-8 lg:py-16">
                    <div className="mx-auto max-w-6xl">
                        <span className="inline-flex items-center gap-2 rounded-full bg-emerald-400/10 px-4 py-2 text-xs font-black text-emerald-300">
                            <CheckCircle2 className="size-4" /> Application
                            saved
                        </span>
                        <h1 className="mt-5 text-4xl font-black tracking-[-0.04em] sm:text-5xl">
                            Review your fees
                        </h1>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-white/60 sm:text-base">
                            {application.name}, choose how you want to pay for{' '}
                            {offering.programme_name}. Reference:{' '}
                            <strong className="text-white">
                                {application.reference}
                            </strong>
                            .
                        </p>
                    </div>
                </section>

                <section className="px-5 py-10 sm:px-8 lg:py-16">
                    <div className="mx-auto mb-6 flex max-w-6xl justify-end">
                        <a
                            href={completion_url}
                            className="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-slate-400 hover:bg-slate-100"
                        >
                            Skip payment and complete application
                        </a>
                    </div>
                    <div className="mx-auto grid max-w-6xl items-start gap-8 lg:grid-cols-[1fr_380px]">
                        <div className="space-y-6">
                            <Form
                                action={`${base_url}/applications/${application.reference}/fees`}
                                method="post"
                                className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="token"
                                            value={application.token}
                                        />
                                        {selectedCycleFeeIds.map((feeId) => (
                                            <input
                                                key={feeId}
                                                type="hidden"
                                                name="cycle_fee_ids[]"
                                                value={feeId}
                                            />
                                        ))}
                                        <p className="text-xs font-black tracking-[0.16em] text-[var(--site-primary)] uppercase">
                                            Fee selection
                                        </p>
                                        <h2 className="mt-2 text-2xl font-black">
                                            Choose your payment plan
                                        </h2>
                                        <p className="mt-2 text-sm leading-6 text-slate-500">
                                            The first payment in the selected
                                            plan is the amount included for
                                            payment now.
                                        </p>

                                        <div className="mt-6 grid gap-4">
                                            {payment_plans.map((plan) => {
                                                const selected =
                                                    selectedPlanId ===
                                                    String(plan.id);

                                                return (
                                                    <label
                                                        key={plan.id}
                                                        className={`cursor-pointer rounded-2xl border-2 p-5 transition ${selected ? 'border-[var(--site-primary)] bg-[var(--site-primary)]/5' : 'border-slate-200 hover:border-slate-300'}`}
                                                    >
                                                        <span className="flex items-start gap-3">
                                                            <input
                                                                type="radio"
                                                                name="programme_cycle_payment_plan_id"
                                                                value={plan.id}
                                                                checked={
                                                                    selected
                                                                }
                                                                onChange={() =>
                                                                    setSelectedPlanId(
                                                                        String(
                                                                            plan.id,
                                                                        ),
                                                                    )
                                                                }
                                                                className="mt-1 size-4 accent-[var(--site-primary)]"
                                                                required
                                                            />
                                                            <span className="min-w-0 flex-1">
                                                                <span className="flex flex-wrap items-start justify-between gap-3">
                                                                    <span>
                                                                        <strong className="block">
                                                                            {
                                                                                plan.name
                                                                            }
                                                                        </strong>
                                                                        {plan.description && (
                                                                            <span className="mt-1 block text-xs leading-5 font-bold text-slate-600">
                                                                                {
                                                                                    plan.description
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </span>
                                                                    {plan.first_payment && (
                                                                        <strong className="text-right text-lg">
                                                                            {formatCurrency(
                                                                                offering.currency,
                                                                                plan
                                                                                    .first_payment
                                                                                    .amount,
                                                                            )}
                                                                        </strong>
                                                                    )}
                                                                </span>
                                                                {plan.first_payment && (
                                                                    <span className="mt-3 block rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">
                                                                        First of{' '}
                                                                        {
                                                                            plan.payment_count
                                                                        }{' '}
                                                                        {plan.payment_count ===
                                                                        1
                                                                            ? 'payment'
                                                                            : 'payments'}
                                                                        :{' '}
                                                                        <strong>
                                                                            {
                                                                                plan
                                                                                    .first_payment
                                                                                    .name
                                                                            }
                                                                        </strong>
                                                                    </span>
                                                                )}
                                                            </span>
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                        {errors.programme_cycle_payment_plan_id && (
                                            <p className="mt-3 text-sm font-bold text-red-600">
                                                {
                                                    errors.programme_cycle_payment_plan_id
                                                }
                                            </p>
                                        )}

                                        <div className="mt-8 border-t border-slate-200 pt-7">
                                            <h3 className="font-black">
                                                Additional general cycle fees
                                            </h3>
                                            <p className="mt-1 text-sm leading-6 text-slate-500">
                                                Compulsory fees are included
                                                automatically. Add any optional
                                                fees you want with this
                                                application.
                                            </p>
                                            {cycle_fees.length > 0 ? (
                                                <div className="mt-4 grid gap-3">
                                                    {cycle_fees.map((fee) => {
                                                        const checked =
                                                            fee.is_compulsory ||
                                                            selectedCycleFeeIds.includes(
                                                                fee.id,
                                                            );

                                                        return (
                                                            <label
                                                                key={fee.id}
                                                                className={`flex items-start gap-3 rounded-2xl border p-4 ${checked ? 'border-[var(--site-primary)]/40 bg-[var(--site-primary)]/5' : 'border-slate-200'}`}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        checked
                                                                    }
                                                                    disabled={
                                                                        fee.is_compulsory
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setSelectedCycleFeeIds(
                                                                            (
                                                                                current,
                                                                            ) =>
                                                                                event
                                                                                    .target
                                                                                    .checked
                                                                                    ? [
                                                                                          ...current,
                                                                                          fee.id,
                                                                                      ]
                                                                                    : current.filter(
                                                                                          (
                                                                                              id,
                                                                                          ) =>
                                                                                              id !==
                                                                                              fee.id,
                                                                                      ),
                                                                        )
                                                                    }
                                                                    className="mt-1 size-4 accent-[var(--site-primary)]"
                                                                />
                                                                <span className="min-w-0 flex-1">
                                                                    <span className="flex justify-between gap-4">
                                                                        <strong>
                                                                            {
                                                                                fee.name
                                                                            }
                                                                        </strong>
                                                                        <strong>
                                                                            {formatCurrency(
                                                                                offering.currency,
                                                                                fee.amount,
                                                                            )}
                                                                        </strong>
                                                                    </span>
                                                                    <span className="mt-1 block text-xs text-slate-500">
                                                                        {
                                                                            fee.category
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {fee.is_compulsory
                                                                            ? 'Compulsory'
                                                                            : 'Optional'}
                                                                    </span>
                                                                </span>
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                <p className="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                                                    No additional general cycle
                                                    fees are available.
                                                </p>
                                            )}
                                            {errors.cycle_fee_ids && (
                                                <p className="mt-3 text-sm font-bold text-red-600">
                                                    {errors.cycle_fee_ids}
                                                </p>
                                            )}
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                (payment_plans.length > 0 &&
                                                    selectedPlanId === '')
                                            }
                                            className="mt-6 rounded-full bg-slate-950 px-6 py-3 text-sm font-black text-white disabled:opacity-50"
                                        >
                                            {processing
                                                ? 'Updating fees…'
                                                : 'Update fee selection'}
                                        </button>
                                    </>
                                )}
                            </Form>

                            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                                <p className="text-xs font-black tracking-[0.16em] text-[var(--site-primary)] uppercase">
                                    Amount due now
                                </p>
                                <h2 className="mt-2 text-2xl font-black">
                                    First payment from your selected plan
                                </h2>
                                {payment_options.length > 0 ? (
                                    <div className="mt-6 grid gap-4">
                                        {payment_options.map((option) => (
                                            <label
                                                key={option.key}
                                                className={`cursor-pointer rounded-2xl border-2 p-5 transition ${selection === option.key ? 'border-[var(--site-primary)] bg-[var(--site-primary)]/5' : 'border-slate-200 hover:border-slate-300'}`}
                                            >
                                                <span className="flex items-start gap-4">
                                                    <input
                                                        type="radio"
                                                        name="payment_option"
                                                        value={option.key}
                                                        checked={
                                                            selection ===
                                                            option.key
                                                        }
                                                        onChange={() =>
                                                            setSelection(
                                                                option.key,
                                                            )
                                                        }
                                                        className="mt-1 size-4 accent-[var(--site-primary)]"
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex flex-wrap items-center justify-between gap-2">
                                                            <strong>
                                                                {option.label}
                                                            </strong>
                                                            <strong className="text-lg">
                                                                {formatCurrency(
                                                                    option.currency,
                                                                    option.total_charge,
                                                                )}
                                                            </strong>
                                                        </span>
                                                        <span className="mt-2 block text-xs leading-5 text-slate-500">
                                                            Fees{' '}
                                                            {formatCurrency(
                                                                option.currency,
                                                                option.base_amount,
                                                            )}{' '}
                                                            + online processing{' '}
                                                            {formatCurrency(
                                                                option.currency,
                                                                option.processing_fee,
                                                            )}
                                                        </span>
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="mt-6 rounded-2xl bg-emerald-50 p-5 text-sm text-emerald-900">
                                        There is no outstanding fee for this
                                        application.
                                    </div>
                                )}
                            </div>

                            <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                                <h2 className="font-black">
                                    Programme fee breakdown
                                </h2>
                                <div className="mt-5 divide-y divide-slate-100">
                                    {offering.fee_items.map((item) => (
                                        <div
                                            key={item.name}
                                            className="flex justify-between gap-4 py-3 text-sm"
                                        >
                                            <span className="text-slate-600">
                                                {item.name}
                                            </span>
                                            <strong>
                                                {formatCurrency(
                                                    offering.currency,
                                                    item.amount_minor / 100,
                                                )}
                                            </strong>
                                        </div>
                                    ))}
                                    {offering.fee_items.length === 0 && (
                                        <p className="py-3 text-sm text-slate-500">
                                            No itemized programme fees were
                                            configured.
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-slate-300 bg-white/70 p-6 text-center">
                                <p className="text-sm text-slate-600">
                                    Not ready to pay? Your application has
                                    already been saved.
                                </p>
                                <a
                                    href={completion_url}
                                    className="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-5 py-3 text-sm font-black text-slate-800 transition hover:border-slate-400 hover:bg-slate-100"
                                >
                                    Skip payment and complete application
                                </a>
                            </div>
                        </div>

                        <aside className="space-y-4 lg:sticky lg:top-28">
                            <div className="rounded-3xl bg-slate-950 p-6 text-white shadow-xl">
                                <div className="flex items-center gap-2">
                                    <ReceiptText className="size-5 text-[var(--site-primary)]" />
                                    <h2 className="font-black">
                                        Payment summary
                                    </h2>
                                </div>
                                <p className="mt-5 text-sm font-bold">
                                    {offering.programme_name}
                                </p>
                                <p className="mt-1 text-xs text-white/50">
                                    {offering.cycle_name} ·{' '}
                                    {offering.branch_name}
                                </p>
                                {selected && (
                                    <div className="mt-6 divide-y divide-white/10 border-y border-white/10 text-sm">
                                        {Number(selected.application_amount) >
                                            0 && (
                                            <div className="flex justify-between py-3">
                                                <span className="text-white/55">
                                                    Application fee
                                                </span>
                                                <strong>
                                                    {formatCurrency(
                                                        selected.currency,
                                                        selected.application_amount,
                                                    )}
                                                </strong>
                                            </div>
                                        )}
                                        {Number(selected.programme_amount) >
                                            0 && (
                                            <div className="flex justify-between py-3">
                                                <span className="text-white/55">
                                                    {selected.programme_label ??
                                                        'Programme payment'}
                                                </span>
                                                <strong>
                                                    {formatCurrency(
                                                        selected.currency,
                                                        selected.programme_amount,
                                                    )}
                                                </strong>
                                            </div>
                                        )}
                                        {selected.cycle_fees?.map((fee) => (
                                            <div
                                                key={fee.name}
                                                className="flex justify-between py-3"
                                            >
                                                <span className="text-white/55">
                                                    {fee.name}
                                                </span>
                                                <strong>
                                                    {formatCurrency(
                                                        selected.currency,
                                                        fee.amount,
                                                    )}
                                                </strong>
                                            </div>
                                        ))}
                                        <div className="flex justify-between py-3">
                                            <span className="text-white/55">
                                                Processing fee
                                            </span>
                                            <strong>
                                                {formatCurrency(
                                                    selected.currency,
                                                    selected.processing_fee,
                                                )}
                                            </strong>
                                        </div>
                                        <div className="flex justify-between py-4 text-base">
                                            <span>Total to pay</span>
                                            <strong className="text-xl">
                                                {formatCurrency(
                                                    selected.currency,
                                                    selected.total_charge,
                                                )}
                                            </strong>
                                        </div>
                                    </div>
                                )}
                                {status === 'verified' ? (
                                    <div
                                        className="mt-6 rounded-2xl bg-emerald-400/15 p-4 text-sm text-emerald-200"
                                        role="status"
                                    >
                                        <p className="flex items-center gap-2 font-black">
                                            <MailCheck className="size-5" />{' '}
                                            Payment verified
                                        </p>
                                        <p className="mt-2 leading-5">
                                            {message}
                                        </p>
                                        {paymentReference && (
                                            <p className="mt-2 text-xs">
                                                Receipt: {paymentReference}
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={pay}
                                        disabled={
                                            !selected ||
                                            !paystack_ready ||
                                            status === 'initializing' ||
                                            status === 'waiting'
                                        }
                                        className="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-[var(--site-primary)] px-5 py-4 text-sm font-black text-[var(--site-primary-fg)] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {status === 'initializing' ||
                                        status === 'waiting' ? (
                                            <LoaderCircle className="size-4 animate-spin" />
                                        ) : (
                                            <CreditCard className="size-4" />
                                        )}
                                        {status === 'initializing'
                                            ? 'Opening Paystack…'
                                            : status === 'waiting'
                                              ? 'Confirming payment…'
                                              : 'Pay securely with Paystack'}
                                    </button>
                                )}
                                {!paystack_ready && (
                                    <p className="mt-3 text-xs leading-5 text-amber-300">
                                        Online payment is temporarily
                                        unavailable. Please contact the academy.
                                    </p>
                                )}
                                {status === 'error' && (
                                    <p
                                        className="mt-4 flex gap-2 rounded-xl bg-red-400/10 p-3 text-xs leading-5 text-red-200"
                                        role="alert"
                                    >
                                        <CircleAlert className="mt-0.5 size-4 shrink-0" />{' '}
                                        {message}
                                    </p>
                                )}
                                <p className="mt-4 flex items-center justify-center gap-2 text-[11px] text-white/40">
                                    <LockKeyhole className="size-3" /> Payment
                                    details are handled securely by Paystack
                                </p>
                            </div>
                            <div className="rounded-3xl border bg-white p-5 text-xs leading-5 text-slate-500">
                                A receipt will be sent to{' '}
                                <strong className="text-slate-800">
                                    {application.email}
                                </strong>{' '}
                                after the server independently verifies the
                                payment.
                            </div>
                        </aside>
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
