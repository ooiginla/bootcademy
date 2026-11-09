import { Head } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    Clock3,
    CreditCard,
    MailCheck,
} from 'lucide-react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import { formatCurrency } from '@/lib/money';
import type { PublicAcademy, PublicSite } from '@/types/public-site';

type PaymentResult = {
    status: 'skipped' | 'pending' | 'failed' | 'review_required' | 'verified';
    amount: string | null;
    currency: string | null;
    reference: string | null;
};

export default function PublicApplicationComplete({
    academy,
    site,
    base_url,
    application,
    payment,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    application: { reference: string; name: string; email: string };
    payment: PaymentResult;
}) {
    const paid = payment.status === 'verified';
    const failed = payment.status === 'failed';
    const pending = ['pending', 'review_required'].includes(payment.status);

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`Application submitted · ${academy.name}`}
        >
            <Head title={`Application submitted · ${academy.name}`} />
            <main className="min-h-[70vh] bg-slate-50 px-5 py-14 sm:px-8 lg:py-24">
                <div className="mx-auto max-w-3xl">
                    <section className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
                        <div className="bg-emerald-600 px-6 py-10 text-center text-white sm:px-10">
                            <span className="mx-auto grid size-16 place-items-center rounded-full bg-white/15">
                                <CheckCircle2 className="size-9" />
                            </span>
                            <p className="mt-5 text-xs font-black tracking-[0.18em] text-emerald-100 uppercase">
                                Application successfully submitted
                            </p>
                            <h1 className="mt-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                                Thank you, {application.name}
                            </h1>
                            <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-emerald-50/85">
                                {academy.name} has received your application.
                                Your reference is{' '}
                                <strong className="text-white">
                                    {application.reference}
                                </strong>
                                .
                            </p>
                        </div>

                        <div className="p-6 sm:p-10">
                            {paid && (
                                <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950">
                                    <p className="flex items-center gap-2 font-black">
                                        <CreditCard className="size-5" />{' '}
                                        Payment successful
                                    </p>
                                    <p className="mt-2 text-sm leading-6">
                                        Paystack confirmed your payment of{' '}
                                        <strong>
                                            {formatCurrency(
                                                payment.currency ?? 'NGN',
                                                payment.amount ?? '0',
                                            )}
                                        </strong>
                                        .
                                    </p>
                                    {payment.reference && (
                                        <p className="mt-2 text-xs">
                                            Payment reference:{' '}
                                            {payment.reference}
                                        </p>
                                    )}
                                </div>
                            )}

                            {failed && (
                                <div className="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-950">
                                    <p className="flex items-center gap-2 font-black">
                                        <CircleAlert className="size-5" />{' '}
                                        Payment was unsuccessful
                                    </p>
                                    <p className="mt-2 text-sm leading-6">
                                        The payment of{' '}
                                        <strong>
                                            {formatCurrency(
                                                payment.currency ?? 'NGN',
                                                payment.amount ?? '0',
                                            )}
                                        </strong>{' '}
                                        was not completed, but your application
                                        was successfully submitted.
                                    </p>
                                </div>
                            )}

                            {pending && (
                                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
                                    <p className="flex items-center gap-2 font-black">
                                        <Clock3 className="size-5" /> Payment
                                        confirmation pending
                                    </p>
                                    <p className="mt-2 text-sm leading-6">
                                        We have not yet confirmed the payment of{' '}
                                        <strong>
                                            {formatCurrency(
                                                payment.currency ?? 'NGN',
                                                payment.amount ?? '0',
                                            )}
                                        </strong>
                                        . Your application is submitted and the
                                        academy can follow up if needed.
                                    </p>
                                </div>
                            )}

                            {payment.status === 'skipped' && (
                                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-800">
                                    <p className="font-black">
                                        Payment skipped for now
                                    </p>
                                    <p className="mt-2 text-sm leading-6 text-slate-600">
                                        No online payment was recorded. This
                                        does not affect the successful
                                        submission of your application.
                                    </p>
                                </div>
                            )}

                            <div className="mt-6 flex items-start gap-3 rounded-2xl bg-slate-950 p-5 text-white">
                                <MailCheck className="mt-0.5 size-5 shrink-0 text-[var(--site-primary)]" />
                                <p className="text-sm leading-6 text-white/70">
                                    Application updates will be sent to{' '}
                                    <strong className="text-white">
                                        {application.email}
                                    </strong>
                                    . Keep your application reference for future
                                    correspondence.
                                </p>
                            </div>

                            <a
                                href={base_url || '/'}
                                className="mt-8 inline-flex w-full items-center justify-center rounded-full bg-[var(--site-primary)] px-6 py-4 text-sm font-black text-[var(--site-primary-fg)] sm:w-auto"
                            >
                                Return to academy website
                            </a>
                        </div>
                    </section>
                </div>
            </main>
        </PublicSiteShell>
    );
}
