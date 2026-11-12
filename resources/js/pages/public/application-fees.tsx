import { Head, router } from '@inertiajs/react';
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
import { loadPaystack, openPaystack } from '@/lib/paystack';
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
type PaymentStatus =
    | 'idle'
    | 'initializing'
    | 'waiting'
    | 'verified'
    | 'skipping'
    | 'closed'
    | 'error';
type OnlineFeeRule = {
    basis_points: number;
    fixed_minor: number;
    cap_minor: number | null;
    waiver_threshold_minor: number | null;
};
type SummaryLine = { label: string; minor: number };

function toMinor(amount: string | number | null | undefined): number {
    return Math.round(Number(amount ?? 0) * 100);
}

/**
 * Mirrors the server's PaystackFeeCalculator so the summary can update as choices change.
 * The server recalculates the fee before charging.
 */
function onlinePaymentFee(rule: OnlineFeeRule, baseMinor: number): number {
    if (baseMinor <= 0 || rule.basis_points <= 0) return 0;
    const rate = rule.basis_points / 10000;
    const withoutFixed = Math.ceil(baseMinor / (1 - rate));
    let fee =
        rule.waiver_threshold_minor !== null &&
        withoutFixed < rule.waiver_threshold_minor
            ? withoutFixed - baseMinor
            : Math.ceil((baseMinor + rule.fixed_minor) / (1 - rate)) -
              baseMinor;
    if (rule.cap_minor !== null) fee = Math.min(fee, rule.cap_minor);

    return Math.max(0, fee);
}

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function jsonPost(url: string, body: Record<string, unknown>) {
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

export default function PublicApplicationFees({
    academy,
    site,
    base_url,
    application,
    offering,
    payment_plans = [],
    cycle_fees = [],
    payment_options,
    application_due = '0.00',
    selection_locked = false,
    online_fee = {
        basis_points: 0,
        fixed_minor: 0,
        cap_minor: null,
        waiver_threshold_minor: null,
    },
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
    application_due?: string;
    selection_locked?: boolean;
    online_fee?: OnlineFeeRule;
    paystack_ready: boolean;
    completion_url: string;
    submitted?: boolean;
}) {
    const [selectedPlanId, setSelectedPlanId] = useState(
        String(
            payment_plans.find((plan) => plan.is_selected)?.id ??
                payment_plans[0]?.id ??
                '',
        ),
    );
    const [selectedCycleFeeIds, setSelectedCycleFeeIds] = useState<number[]>(
        cycle_fees
            .filter((fee) => fee.is_compulsory || fee.is_selected)
            .map((fee) => fee.id),
    );
    const [submittedDialogOpen, setSubmittedDialogOpen] = useState(submitted);
    const [status, setStatus] = useState<PaymentStatus>('idle');
    const [message, setMessage] = useState('');
    const [paymentReference, setPaymentReference] = useState('');
    const stopped = useRef(false);
    const currency = offering.currency;
    const selectedPlan = payment_plans.find(
        (plan) => String(plan.id) === selectedPlanId,
    );
    const busy =
        status === 'initializing' ||
        status === 'waiting' ||
        status === 'skipping';
    const canChoose = !selection_locked && !busy;

    // The summary follows the choices on screen. Once fees are locked it shows what is owed.
    const summary = useMemo(() => {
        const lines: SummaryLine[] = [];
        if (selection_locked) {
            const option = payment_options[0];
            if (!option) return { lines, feeMinor: 0, totalMinor: 0 };
            if (toMinor(option.application_amount) > 0)
                lines.push({
                    label: 'Application fee',
                    minor: toMinor(option.application_amount),
                });
            if (toMinor(option.programme_amount) > 0)
                lines.push({
                    label: option.programme_label ?? 'Programme payment',
                    minor: toMinor(option.programme_amount),
                });
            option.cycle_fees?.forEach((fee) =>
                lines.push({ label: fee.name, minor: toMinor(fee.amount) }),
            );
            const feeMinor = toMinor(option.processing_fee);

            return {
                lines,
                feeMinor,
                totalMinor: toMinor(option.total_charge),
            };
        }

        if (toMinor(application_due) > 0)
            lines.push({
                label: 'Application fee',
                minor: toMinor(application_due),
            });
        if (selectedPlan?.first_payment) {
            lines.push({
                label: `${selectedPlan.name} · ${selectedPlan.first_payment.name}`,
                minor: toMinor(selectedPlan.first_payment.amount),
            });
        } else if (payment_plans.length === 0 && payment_options[0]) {
            const programmeMinor = toMinor(payment_options[0].programme_amount);
            if (programmeMinor > 0)
                lines.push({
                    label:
                        payment_options[0].programme_label ??
                        'Programme payment',
                    minor: programmeMinor,
                });
        }
        cycle_fees
            .filter((fee) => selectedCycleFeeIds.includes(fee.id))
            .forEach((fee) =>
                lines.push({ label: fee.name, minor: toMinor(fee.amount) }),
            );
        const baseMinor = lines.reduce((total, line) => total + line.minor, 0);
        const feeMinor = onlinePaymentFee(online_fee, baseMinor);

        return { lines, feeMinor, totalMinor: baseMinor + feeMinor };
    }, [
        selection_locked,
        payment_options,
        application_due,
        selectedPlan,
        payment_plans.length,
        cycle_fees,
        selectedCycleFeeIds,
        online_fee,
    ]);

    useEffect(
        () => () => {
            stopped.current = true;
        },
        [],
    );

    const selectionPayload = () => ({
        token: application.token,
        programme_cycle_payment_plan_id: selectedPlanId
            ? Number(selectedPlanId)
            : null,
        cycle_fee_ids: selectedCycleFeeIds,
    });

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
        if (busy || summary.totalMinor <= 0) return;
        stopped.current = false;
        setStatus('initializing');
        setMessage('');
        try {
            await loadPaystack();
            const { response, payload } = await jsonPost(
                `${base_url}/applications/${application.reference}/payments`,
                {
                    ...selectionPayload(),
                    selection: 'initial',
                    apply_selection: !selection_locked,
                },
            );
            if (!response.ok || !payload.access_code || !payload.verify_url) {
                // The server has recorded the failed attempt; show the submitted application.
                if (payload.completion_url) {
                    window.location.assign(payload.completion_url);
                    return;
                }
                throw new Error(
                    payload.message ?? 'Payment could not be initialized.',
                );
            }
            setStatus('waiting');
            const outcome = await openPaystack(payload.access_code);
            if (outcome === 'closed') {
                // Nothing was charged. Paying again reuses the same Paystack transaction.
                setStatus('closed');
                return;
            }
            await pollVerification(
                payload.verify_url,
                payload.completion_url || completion_url,
            );
        } catch (error) {
            setStatus('error');
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Payment could not be completed.',
            );
        }
    };

    // Skipping still saves the plan and optional fees chosen on this page.
    const skip = () => {
        if (busy) return;
        if (selection_locked) {
            window.location.assign(completion_url);
            return;
        }
        setStatus('skipping');
        setMessage('');
        router.post(
            `${base_url}/applications/${application.reference}/fees`,
            { ...selectionPayload(), complete: true },
            {
                onError: (errors) => {
                    setStatus('error');
                    setMessage(
                        Object.values(errors)[0] ??
                            'We could not save your fee selection.',
                    );
                },
                onFinish: () =>
                    setStatus((current) =>
                        current === 'skipping' ? 'idle' : current,
                    ),
            },
        );
    };

    const toggleCycleFee = (feeId: number, checked: boolean) =>
        setSelectedCycleFeeIds((current) =>
            checked
                ? [...current, feeId]
                : current.filter((id) => id !== feeId),
        );

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
                    {/* Mobile order: choices, summary, then the action buttons last. */}
                    <div className="mx-auto grid max-w-6xl items-start gap-6 lg:grid-cols-[1fr_380px] lg:gap-x-8">
                        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:col-start-1 lg:row-start-1">
                            <p className="text-xs font-black tracking-[0.16em] text-[var(--site-primary)] uppercase">
                                Fee selection
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                Choose your payment plan
                            </h2>
                            {selection_locked ? (
                                <p className="mt-2 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                                    A payment has already been recorded, so
                                    these choices can no longer change. Contact
                                    the academy if you need a different plan.
                                </p>
                            ) : (
                                <p className="mt-2 text-sm leading-6 text-slate-500">
                                    You pay the first payment of the plan you
                                    choose now. The summary updates as you
                                    choose.
                                </p>
                            )}

                            {payment_plans.length > 0 && (
                                <fieldset
                                    className="mt-6 grid gap-4"
                                    disabled={!canChoose}
                                >
                                    <legend className="sr-only">
                                        Payment plan
                                    </legend>
                                    {payment_plans.map((plan) => {
                                        const selected =
                                            selectedPlanId === String(plan.id);

                                        return (
                                            <label
                                                key={plan.id}
                                                className={`rounded-2xl border-2 p-5 transition ${canChoose ? 'cursor-pointer' : 'cursor-not-allowed opacity-80'} ${selected ? 'border-[var(--site-primary)] bg-[var(--site-primary)]/5' : 'border-slate-200 hover:border-slate-300'}`}
                                            >
                                                <span className="flex items-start gap-3">
                                                    <input
                                                        type="radio"
                                                        name="programme_cycle_payment_plan_id"
                                                        value={plan.id}
                                                        checked={selected}
                                                        onChange={() =>
                                                            setSelectedPlanId(
                                                                String(plan.id),
                                                            )
                                                        }
                                                        className="mt-1 size-4 accent-[var(--site-primary)]"
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex flex-wrap items-start justify-between gap-3">
                                                            <span>
                                                                <strong className="block">
                                                                    {plan.name}
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
                                                                        currency,
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
                                </fieldset>
                            )}

                            <div className="mt-8 border-t border-slate-200 pt-7">
                                <h3 className="font-black">
                                    Additional cycle fees
                                </h3>
                                <p className="mt-1 text-sm leading-6 text-slate-500">
                                    Compulsory fees are included automatically.
                                    Tick any optional fees you want to add.
                                </p>
                                {cycle_fees.length > 0 ? (
                                    <fieldset
                                        className="mt-4 grid gap-3"
                                        disabled={!canChoose}
                                    >
                                        <legend className="sr-only">
                                            Additional cycle fees
                                        </legend>
                                        {cycle_fees.map((fee) => {
                                            const checked =
                                                fee.is_compulsory ||
                                                selectedCycleFeeIds.includes(
                                                    fee.id,
                                                );

                                            return (
                                                <label
                                                    key={fee.id}
                                                    className={`flex items-start gap-3 rounded-2xl border p-4 ${checked ? 'border-[var(--site-primary)]/40 bg-[var(--site-primary)]/5' : 'border-slate-200'} ${fee.is_compulsory || !canChoose ? '' : 'cursor-pointer'}`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        disabled={
                                                            fee.is_compulsory
                                                        }
                                                        onChange={(event) =>
                                                            toggleCycleFee(
                                                                fee.id,
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                        className="mt-1 size-4 accent-[var(--site-primary)]"
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex justify-between gap-4">
                                                            <strong>
                                                                {fee.name}
                                                            </strong>
                                                            <strong>
                                                                {formatCurrency(
                                                                    currency,
                                                                    fee.amount,
                                                                )}
                                                            </strong>
                                                        </span>
                                                        <span className="mt-1 block text-xs text-slate-500">
                                                            {fee.category} ·{' '}
                                                            {fee.is_compulsory
                                                                ? 'Compulsory'
                                                                : 'Optional'}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </fieldset>
                                ) : (
                                    <p className="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                                        No additional cycle fees are available.
                                    </p>
                                )}
                            </div>
                        </div>

                        <aside className="space-y-4 lg:sticky lg:top-28 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                            <div
                                className="rounded-3xl bg-slate-950 p-6 text-white shadow-xl"
                                aria-live="polite"
                            >
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
                                {summary.totalMinor > 0 ? (
                                    <div className="mt-6 divide-y divide-white/10 border-y border-white/10 text-sm">
                                        {summary.lines.map((line, index) => (
                                            <div
                                                key={`${line.label}-${index}`}
                                                className="flex justify-between gap-4 py-3"
                                            >
                                                <span className="text-white/55">
                                                    {line.label}
                                                </span>
                                                <strong className="shrink-0">
                                                    {formatCurrency(
                                                        currency,
                                                        line.minor / 100,
                                                    )}
                                                </strong>
                                            </div>
                                        ))}
                                        {summary.feeMinor > 0 && (
                                            <div className="flex justify-between gap-4 py-3">
                                                <span className="text-white/55">
                                                    Online payment fee
                                                    <span className="block text-[11px] text-white/40">
                                                        Paystack’s charge for
                                                        paying online
                                                    </span>
                                                </span>
                                                <strong className="shrink-0">
                                                    {formatCurrency(
                                                        currency,
                                                        summary.feeMinor / 100,
                                                    )}
                                                </strong>
                                            </div>
                                        )}
                                        <div className="flex justify-between py-4 text-base">
                                            <span>Total to pay now</span>
                                            <strong className="text-xl">
                                                {formatCurrency(
                                                    currency,
                                                    summary.totalMinor / 100,
                                                )}
                                            </strong>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mt-6 rounded-2xl bg-emerald-400/15 p-4 text-sm text-emerald-200">
                                        Nothing is due for this application
                                        right now.
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

                        <div className="space-y-3 lg:col-start-1 lg:row-start-2">
                            {status === 'verified' && (
                                <div
                                    className="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900"
                                    role="status"
                                >
                                    <p className="flex items-center gap-2 font-black">
                                        <MailCheck className="size-5" /> Payment
                                        verified
                                    </p>
                                    <p className="mt-2 leading-5">{message}</p>
                                    {paymentReference && (
                                        <p className="mt-2 text-xs">
                                            Receipt: {paymentReference}
                                        </p>
                                    )}
                                </div>
                            )}
                            {status === 'error' && (
                                <p
                                    className="flex gap-2 rounded-2xl bg-red-50 p-4 text-sm leading-5 text-red-700"
                                    role="alert"
                                >
                                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                    {message}
                                </p>
                            )}
                            {status === 'closed' && (
                                <p
                                    className="rounded-2xl bg-slate-100 p-4 text-sm leading-5 text-slate-700"
                                    role="status"
                                >
                                    The Paystack window was closed before
                                    payment, so nothing was charged. You can pay
                                    now or skip payment for now.
                                </p>
                            )}
                            {!paystack_ready && (
                                <p className="rounded-2xl bg-amber-50 p-4 text-sm leading-5 text-amber-900">
                                    Online payment is temporarily unavailable.
                                    You can skip payment and the academy will
                                    contact you.
                                </p>
                            )}
                            <div className="flex flex-col-reverse gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
                                <button
                                    type="button"
                                    onClick={skip}
                                    disabled={busy}
                                    className="inline-flex items-center justify-center gap-2 rounded-full border border-slate-300 bg-white px-5 py-3.5 text-sm font-black text-slate-800 transition hover:border-slate-400 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {status === 'skipping' && (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    )}
                                    Skip payment for now
                                </button>
                                {status !== 'verified' && (
                                    <button
                                        type="button"
                                        onClick={pay}
                                        disabled={
                                            busy ||
                                            !paystack_ready ||
                                            summary.totalMinor <= 0 ||
                                            (payment_plans.length > 0 &&
                                                selectedPlanId === '')
                                        }
                                        className="inline-flex items-center justify-center gap-2 rounded-full bg-[var(--site-primary)] px-6 py-3.5 text-sm font-black text-[var(--site-primary-fg)] disabled:cursor-not-allowed disabled:opacity-50"
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
                                              : summary.totalMinor > 0
                                                ? `Pay ${formatCurrency(currency, summary.totalMinor / 100)} securely with Paystack`
                                                : 'Pay securely with Paystack'}
                                    </button>
                                )}
                            </div>
                            <p className="text-center text-xs text-slate-500 sm:text-left">
                                Not ready to pay? Your application is already
                                saved. Skipping keeps the choices above.
                            </p>
                        </div>
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
