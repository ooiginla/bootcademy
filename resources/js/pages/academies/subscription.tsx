import { Head, router } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    CircleAlert,
    Gauge,
    Loader2,
    Minus,
    ReceiptText,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import { jsonPost } from '@/lib/json-post';
import { loadPaystack, openPaystack } from '@/lib/paystack';
import type {
    BillingInterval,
    PlanComparison,
    PricedPlan,
    SubscriptionPaymentRow,
    SubscriptionPeriod,
} from '@/types/subscription';
import {
    formatDate,
    intervalLabels,
    intervalMonths,
    limitLabel,
    minorToMoney,
    paymentStatusLabels,
} from '@/types/subscription';

type State = 'trialing' | 'active' | 'lapsed' | 'suspended';

type Props = {
    state: State;
    current: SubscriptionPeriod | null;
    access_ends_at: string | null;
    upcoming: SubscriptionPeriod[];
    plan_name: string | null;
    next_period_starts_at: string | null;
    can_pay: boolean;
    online_payment_available: boolean;
    plans: PricedPlan[];
    comparison: PlanComparison;
    usage: {
        active_players: number;
        branches: number;
        staff_accounts: number;
    } | null;
    limits: {
        active_players: number | null;
        branches: number | null;
        staff_accounts: number | null;
    };
    payments: SubscriptionPaymentRow[];
};

type Checkout =
    | { status: 'idle' }
    | { status: 'working'; planId: number }
    | { status: 'message'; tone: 'success' | 'error' | 'info'; text: string };

const kindLabels: Record<SubscriptionPeriod['kind'], string> = {
    trial: 'Free trial',
    paid: 'Paid',
    granted: 'Granted by platform',
};

function daysLeft(value: string | null): number | null {
    if (!value) return null;

    return Math.max(
        0,
        Math.ceil((new Date(value).getTime() - Date.now()) / 86_400_000),
    );
}

function savingsLabel(plan: PricedPlan, interval: BillingInterval) {
    const full = plan.prices.monthly.amount_minor * intervalMonths[interval];
    const actual = plan.prices[interval].amount_minor;
    if (interval === 'monthly' || full <= 0 || actual >= full) return null;

    return `Save ${Math.round(((full - actual) / full) * 100)}%`;
}

export default function AcademySubscription(props: Props) {
    const { state, current, can_pay, plans } = props;
    const terminology = useAcademyTerminology();
    const [interval, setInterval] = useState<BillingInterval>('annual');
    const [checkout, setCheckout] = useState<Checkout>({ status: 'idle' });
    const [showPlans, setShowPlans] = useState(state !== 'active');
    const busy = checkout.status === 'working';
    const remaining = daysLeft(props.access_ends_at);

    async function pay(plan: PricedPlan) {
        setCheckout({ status: 'working', planId: plan.id });
        try {
            const { response, payload } = await jsonPost<{
                access_code?: string;
                verify_url?: string;
                message?: string;
                errors?: Record<string, string[]>;
            }>('/app/settings/subscription/payments', {
                plan_id: plan.id,
                billing_interval: interval,
            });
            if (!response.ok || !payload.access_code || !payload.verify_url) {
                throw new Error(
                    payload.errors?.plan_id?.[0] ??
                        payload.message ??
                        'The payment could not be started.',
                );
            }
            await loadPaystack();
            const outcome = await openPaystack(payload.access_code);
            if (outcome === 'closed') {
                setCheckout({
                    status: 'message',
                    tone: 'info',
                    text: 'The payment window was closed. You have not been charged.',
                });

                return;
            }
            const verified = await jsonPost<{
                status?: string;
                message?: string;
            }>(payload.verify_url);
            if (verified.payload.status === 'verified') {
                setCheckout({
                    status: 'message',
                    tone: 'success',
                    text: 'Payment received. Your subscription is active.',
                });
            } else {
                setCheckout({
                    status: 'message',
                    tone: 'info',
                    text: 'We are confirming your payment with Paystack. Your subscription updates as soon as it is confirmed.',
                });
            }
            router.reload();
        } catch (error) {
            setCheckout({
                status: 'message',
                tone: 'error',
                text:
                    error instanceof Error
                        ? error.message
                        : 'The payment could not be completed.',
            });
        }
    }

    return (
        <>
            <Head title="Subscription" />
            <main className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <p className="text-sm font-medium text-primary">
                        Academy settings
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Subscription
                    </h1>
                </div>

                <StateNotice
                    state={state}
                    canPay={can_pay}
                    planName={props.plan_name}
                    accessEndsAt={props.access_ends_at}
                    remaining={remaining}
                />

                {current && (
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <ReceiptText className="size-4" />
                                    Current plan
                                </p>
                                <h2 className="mt-1 text-xl font-semibold">
                                    {current.plan_name}
                                </h2>
                            </div>
                            <Badge
                                variant={
                                    current.kind === 'trial'
                                        ? 'outline'
                                        : 'default'
                                }
                            >
                                {kindLabels[current.kind]}
                                {current.billing_interval_label
                                    ? ` · ${current.billing_interval_label}`
                                    : ''}
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <dl className="grid gap-4 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Started
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(current.starts_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {current.kind === 'trial'
                                            ? 'Trial ends'
                                            : 'Expires'}
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(current.ends_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Access until
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(props.access_ends_at)}
                                        {remaining !== null && (
                                            <span className="ml-1 text-muted-foreground">
                                                ({remaining}{' '}
                                                {remaining === 1
                                                    ? 'day'
                                                    : 'days'}{' '}
                                                left)
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            {props.upcoming.length > 0 && (
                                <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                                    <p className="font-medium">
                                        Already paid for
                                    </p>
                                    <ul className="mt-1 space-y-1 text-muted-foreground">
                                        {props.upcoming.map((period) => (
                                            <li key={period.id}>
                                                {period.plan_name}
                                                {period.billing_interval_label
                                                    ? ` (${period.billing_interval_label})`
                                                    : ''}{' '}
                                                from{' '}
                                                {formatDate(period.starts_at)}{' '}
                                                to {formatDate(period.ends_at)}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {props.usage && (
                                <UsageMeters
                                    usage={props.usage}
                                    limits={props.limits}
                                    playersLabel={terminology.plural.toLocaleLowerCase()}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}

                {can_pay && plans.length > 0 && (
                    <section className="space-y-4">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    {state === 'active'
                                        ? 'Renew or change plan'
                                        : 'Choose a plan'}
                                </h2>
                                {props.next_period_starts_at &&
                                    state === 'active' && (
                                        <p className="text-sm text-muted-foreground">
                                            A new payment starts on{' '}
                                            {formatDate(
                                                props.next_period_starts_at,
                                            )}
                                            , when your paid time runs out.
                                        </p>
                                    )}
                                {state === 'trialing' && (
                                    <p className="text-sm text-muted-foreground">
                                        Paying ends the free trial and starts
                                        your paid period today.
                                    </p>
                                )}
                            </div>
                            {state === 'active' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setShowPlans(!showPlans)}
                                >
                                    {showPlans ? 'Hide plans' : 'Show plans'}
                                </Button>
                            )}
                        </div>

                        {showPlans && (
                            <>
                                <div
                                    role="radiogroup"
                                    aria-label="Billing period"
                                    className="inline-flex rounded-lg border bg-muted/40 p-1"
                                >
                                    {(
                                        Object.keys(
                                            intervalLabels,
                                        ) as BillingInterval[]
                                    ).map((key) => (
                                        <button
                                            key={key}
                                            type="button"
                                            role="radio"
                                            aria-checked={interval === key}
                                            onClick={() => setInterval(key)}
                                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition ${interval === key ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                        >
                                            {intervalLabels[key]}
                                        </button>
                                    ))}
                                </div>

                                {checkout.status === 'message' && (
                                    <p
                                        role="status"
                                        className={`rounded-lg p-3 text-sm ${checkout.tone === 'error' ? 'bg-destructive/10 text-destructive' : checkout.tone === 'success' ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-muted'}`}
                                    >
                                        {checkout.text}
                                    </p>
                                )}
                                {!props.online_payment_available && (
                                    <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                        Online payment is not available right
                                        now. Please contact support to renew.
                                    </p>
                                )}

                                <div className="grid gap-4 lg:grid-cols-3">
                                    {plans.map((plan) => (
                                        <PlanCard
                                            key={plan.id}
                                            plan={plan}
                                            interval={interval}
                                            comparison={props.comparison}
                                            playersLabel={terminology.plural.toLocaleLowerCase()}
                                            disabled={
                                                busy ||
                                                !props.online_payment_available
                                            }
                                            working={
                                                checkout.status === 'working' &&
                                                checkout.planId === plan.id
                                            }
                                            onPay={() => pay(plan)}
                                        />
                                    ))}
                                </div>

                                <Card>
                                    <CardHeader>
                                        <h3 className="font-semibold">
                                            Included in every plan
                                        </h3>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                            {props.comparison.included.map(
                                                (feature) => (
                                                    <li
                                                        key={feature}
                                                        className="flex items-start gap-2"
                                                    >
                                                        <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                                        {feature}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </section>
                )}

                {can_pay && props.payments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <h2 className="font-semibold">Payment history</h2>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-4 font-medium">
                                            Date
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Plan
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Period
                                        </th>
                                        <th className="py-2 pr-4 text-right font-medium">
                                            Amount
                                        </th>
                                        <th className="py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {props.payments.map((payment) => (
                                        <tr key={payment.id}>
                                            <td className="py-2 pr-4">
                                                {formatDate(payment.created_at)}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {payment.plan_name} ·{' '}
                                                {payment.billing_interval_label}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {payment.period_starts_at
                                                    ? `${formatDate(payment.period_starts_at)} – ${formatDate(payment.period_ends_at)}`
                                                    : '—'}
                                            </td>
                                            <td className="py-2 pr-4 text-right">
                                                {minorToMoney(
                                                    payment.currency,
                                                    payment.charged_amount_minor,
                                                )}
                                            </td>
                                            <td className="py-2">
                                                <Badge variant="outline">
                                                    {paymentStatusLabels[
                                                        payment.status
                                                    ] ?? payment.status}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}

function StateNotice({
    state,
    canPay,
    planName,
    accessEndsAt,
    remaining,
}: {
    state: State;
    canPay: boolean;
    planName: string | null;
    accessEndsAt: string | null;
    remaining: number | null;
}) {
    if (state === 'active') return null;
    const message =
        state === 'trialing'
            ? `You are on a free ${planName ?? ''} trial until ${formatDate(accessEndsAt)}${remaining !== null ? ` (${remaining} ${remaining === 1 ? 'day' : 'days'} left)` : ''}. ${canPay ? 'Choose a plan to keep using the academy app after it ends.' : 'Ask your academy owner to choose a plan before it ends.'}`
            : state === 'suspended'
              ? 'This academy’s subscription has been suspended. Please contact support.'
              : canPay
                ? `Your subscription has ended, so the academy app is paused. You are now on the ${planName ?? 'Essential'} plan. Choose a plan and pay to continue. Your records are kept safe, and your public website and online payments keep working.`
                : 'This academy’s subscription has ended. Ask your academy owner to renew it to use the academy app again.';

    return (
        <div
            role={state === 'trialing' ? 'status' : 'alert'}
            className={`flex gap-3 rounded-lg border p-4 text-sm ${state === 'trialing' ? 'border-primary/30 bg-primary/5' : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100'}`}
        >
            {state === 'trialing' ? (
                <Sparkles className="mt-0.5 size-4 shrink-0 text-primary" />
            ) : (
                <CircleAlert className="mt-0.5 size-4 shrink-0" />
            )}
            <p>{message}</p>
        </div>
    );
}

function UsageMeters({
    usage,
    limits,
    playersLabel,
}: {
    usage: NonNullable<Props['usage']>;
    limits: Props['limits'];
    playersLabel: string;
}) {
    const rows: Array<[string, number, number | null]> = [
        [`Active ${playersLabel}`, usage.active_players, limits.active_players],
        ['Active branches', usage.branches, limits.branches],
        ['Staff accounts', usage.staff_accounts, limits.staff_accounts],
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-3">
            {rows.map(([label, used, limit]) => {
                const percent =
                    limit && limit > 0
                        ? Math.min(100, Math.round((used / limit) * 100))
                        : 0;

                return (
                    <div key={label} className="space-y-1.5">
                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Gauge className="size-3.5" />
                            {label}
                        </p>
                        <p className="text-sm font-medium">
                            {used.toLocaleString('en-NG')}
                            {limit != null &&
                                ` of ${limit.toLocaleString('en-NG')}`}
                        </p>
                        {limit != null && (
                            <div
                                className="h-1.5 rounded-full bg-muted"
                                role="progressbar"
                                aria-label={label}
                                aria-valuenow={used}
                                aria-valuemax={limit}
                            >
                                <div
                                    className={`h-1.5 rounded-full ${percent >= 100 ? 'bg-destructive' : 'bg-primary'}`}
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function PlanCard({
    plan,
    interval,
    comparison,
    playersLabel,
    disabled,
    working,
    onPay,
}: {
    plan: PricedPlan;
    interval: BillingInterval;
    comparison: PlanComparison;
    playersLabel: string;
    disabled: boolean;
    working: boolean;
    onPay: () => void;
}) {
    const price = plan.prices[interval];
    const savings = savingsLabel(plan, interval);

    return (
        <Card
            className={`flex flex-col ${plan.is_recommended ? 'border-primary shadow-md ring-1 ring-primary/30' : ''}`}
        >
            <CardHeader className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-lg font-semibold">{plan.name}</h3>
                    {plan.is_recommended && <Badge>Most popular</Badge>}
                    {plan.is_current && (
                        <Badge variant="outline">Current</Badge>
                    )}
                </div>
                {plan.description && (
                    <p className="text-sm text-muted-foreground">
                        {plan.description}
                    </p>
                )}
                <div>
                    <p className="text-3xl font-semibold tracking-tight">
                        {minorToMoney(plan.currency, price.amount_minor)}
                        <span className="text-sm font-normal text-muted-foreground">
                            {' '}
                            / {intervalLabels[interval].toLocaleLowerCase()}
                        </span>
                    </p>
                    <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                        {savings && (
                            <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                {savings}
                            </span>
                        )}
                        {plan.has_custom_price &&
                            price.list_amount_minor !== price.amount_minor && (
                                <span>
                                    Your price · list{' '}
                                    <s>
                                        {minorToMoney(
                                            plan.currency,
                                            price.list_amount_minor,
                                        )}
                                    </s>
                                </span>
                            )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-5">
                <ul className="space-y-2 text-sm">
                    <li className="flex gap-2">
                        <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        {limitLabel(
                            plan.active_player_limit,
                            `active ${playersLabel}`,
                        )}
                    </li>
                    <li className="flex gap-2">
                        <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        {limitLabel(
                            plan.branch_limit,
                            plan.branch_limit === 1 ? 'branch' : 'branches',
                        )}
                    </li>
                    <li className="flex gap-2">
                        <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        {limitLabel(plan.staff_account_limit, 'staff accounts')}
                    </li>
                    {Object.entries(comparison.flags).map(([key, label]) => (
                        <li
                            key={key}
                            className={`flex gap-2 ${plan.flags[key] ? '' : 'text-muted-foreground'}`}
                        >
                            {plan.flags[key] ? (
                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                            ) : (
                                <Minus className="mt-0.5 size-4 shrink-0" />
                            )}
                            {label}
                        </li>
                    ))}
                    {plan.migration_support && (
                        <li className="flex gap-2">
                            <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                            Data migration: {plan.migration_support}
                        </li>
                    )}
                    {plan.support_level && (
                        <li className="flex gap-2">
                            <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                            Support: {plan.support_level}
                        </li>
                    )}
                </ul>
                <div className="mt-auto space-y-2">
                    <p className="flex justify-between text-xs text-muted-foreground">
                        <span>Online payment fee</span>
                        <span>
                            {minorToMoney(plan.currency, price.fee_minor)}
                        </span>
                    </p>
                    <Button
                        className="w-full"
                        variant={plan.is_recommended ? 'default' : 'outline'}
                        disabled={disabled}
                        onClick={onPay}
                    >
                        {working ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <CalendarClock className="size-4" />
                        )}
                        Pay {minorToMoney(plan.currency, price.total_minor)}{' '}
                        with Paystack
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
