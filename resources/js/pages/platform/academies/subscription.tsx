import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    BillingInterval,
    SubscriptionPaymentRow,
    SubscriptionPeriod,
} from '@/types/subscription';
import {
    formatDate,
    intervalLabels,
    minorToMoney,
    paymentStatusLabels,
} from '@/types/subscription';

type PlanRow = {
    id: number;
    name: string;
    is_active: boolean;
    list: Record<BillingInterval, number>;
    custom: Record<BillingInterval, number> | null;
};

type Props = {
    managedAcademy: { id: number; name: string };
    state: 'trialing' | 'active' | 'lapsed' | 'suspended';
    status: string;
    plan_name: string | null;
    access_ends_at: string | null;
    periods: SubscriptionPeriod[];
    payments: SubscriptionPaymentRow[];
    plans: PlanRow[];
    statuses: { value: string; label: string }[];
};

const stateLabels: Record<Props['state'], string> = {
    trialing: 'Free trial',
    active: 'Paid',
    lapsed: 'Expired',
    suspended: 'Suspended',
};

const kindLabels: Record<SubscriptionPeriod['kind'], string> = {
    trial: 'Trial',
    paid: 'Paid',
    granted: 'Granted',
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const intervals: BillingInterval[] = ['monthly', 'quarterly', 'annual'];

export default function PlatformAcademySubscription(props: Props) {
    const base = `/platform/academies/${props.managedAcademy.id}/subscription`;
    const grant = useForm({
        plan_id: String(props.plans.find((plan) => plan.is_active)?.id ?? ''),
        ends_on: '',
        note: '',
    });

    function submitGrant(event: FormEvent) {
        event.preventDefault();
        grant.post(`${base}/periods`, {
            preserveScroll: true,
            onSuccess: () => grant.reset('ends_on', 'note'),
        });
    }

    return (
        <>
            <Head title={`${props.managedAcademy.name} subscription`} />
            <main className="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" className="-ml-3" asChild>
                    <Link href="/platform/academies">
                        <ArrowLeft className="size-4" />
                        All academies
                    </Link>
                </Button>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-primary">
                                Subscription
                            </p>
                            <h1 className="mt-1 text-xl font-semibold">
                                {props.managedAcademy.name}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {props.plan_name ?? 'No plan'} ·{' '}
                                {props.access_ends_at
                                    ? `access until ${formatDate(props.access_ends_at)}`
                                    : 'no access'}
                            </p>
                        </div>
                        <Badge
                            variant={
                                props.state === 'active' ||
                                props.state === 'trialing'
                                    ? 'default'
                                    : 'destructive'
                            }
                        >
                            {stateLabels[props.state]}
                        </Badge>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-3">
                        <Label htmlFor="status" className="text-sm">
                            Platform status
                        </Label>
                        <select
                            id="status"
                            className={`${selectClass} w-auto`}
                            value={props.status}
                            onChange={(event) =>
                                router.patch(
                                    base,
                                    { status: event.target.value },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {props.statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-muted-foreground">
                            Suspended academies see only the subscription page,
                            even with paid time left.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <h2 className="font-semibold">Grant access</h2>
                        <p className="text-sm text-muted-foreground">
                            For payments taken outside Paystack, or
                            complimentary time. Starts when the academy’s paid
                            time runs out, or today.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitGrant}
                            className="grid gap-4 sm:grid-cols-[1fr_1fr_2fr_auto] sm:items-end"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="grant_plan">Plan</Label>
                                <select
                                    id="grant_plan"
                                    className={selectClass}
                                    value={grant.data.plan_id}
                                    onChange={(event) =>
                                        grant.setData(
                                            'plan_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {props.plans.map((plan) => (
                                        <option key={plan.id} value={plan.id}>
                                            {plan.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={grant.errors.plan_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ends_on">Ends on</Label>
                                <Input
                                    id="ends_on"
                                    type="date"
                                    value={grant.data.ends_on}
                                    onChange={(event) =>
                                        grant.setData(
                                            'ends_on',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={grant.errors.ends_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="note">Note</Label>
                                <Input
                                    id="note"
                                    value={grant.data.note}
                                    maxLength={255}
                                    placeholder="e.g. Annual plan paid by transfer"
                                    onChange={(event) =>
                                        grant.setData(
                                            'note',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={grant.errors.note} />
                            </div>
                            <Button type="submit" disabled={grant.processing}>
                                Grant
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <h2 className="font-semibold">
                            Prices for this academy
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            A custom price replaces the list price on this
                            academy’s subscription page. Payments already made
                            are not changed.
                        </p>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {props.plans.map((plan) => (
                            <PriceRow
                                key={plan.id}
                                plan={plan}
                                action={`${base}/prices/${plan.id}`}
                            />
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <h2 className="font-semibold">Periods</h2>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {props.periods.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No periods yet.
                            </p>
                        ) : (
                            <table className="w-full min-w-[560px] text-left text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Plan
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            From
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            To
                                        </th>
                                        <th className="py-2 font-medium">
                                            Note
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {props.periods.map((period) => (
                                        <tr key={period.id}>
                                            <td className="py-2 pr-4">
                                                {kindLabels[period.kind]}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {period.plan_name}
                                                {period.billing_interval_label
                                                    ? ` · ${period.billing_interval_label}`
                                                    : ''}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {formatDate(period.starts_at)}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {formatDate(period.ends_at)}
                                            </td>
                                            <td className="py-2 text-muted-foreground">
                                                {period.note ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <h2 className="font-semibold">Payments</h2>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {props.payments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No subscription payments yet.
                            </p>
                        ) : (
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-4 font-medium">
                                            Date
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Reference
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Plan
                                        </th>
                                        <th className="py-2 pr-4 text-right font-medium">
                                            Charged
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
                                            <td className="py-2 pr-4 font-mono text-xs">
                                                {payment.reference}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {payment.plan_name} ·{' '}
                                                {payment.billing_interval_label}
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
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function PriceRow({ plan, action }: { plan: PlanRow; action: string }) {
    const source = plan.custom ?? plan.list;
    const form = useForm({
        monthly_ngn: String(source.monthly / 100),
        quarterly_ngn: String(source.quarterly / 100),
        annual_ngn: String(source.annual / 100),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(action, { preserveScroll: true });
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 py-4 first:pt-0 last:pb-0 sm:grid-cols-[8rem_repeat(3,1fr)_auto] sm:items-end"
        >
            <div>
                <p className="font-medium">{plan.name}</p>
                <p className="text-xs text-muted-foreground">
                    {plan.custom ? 'Custom price' : 'List price'}
                </p>
            </div>
            {intervals.map((interval) => {
                const key = `${interval}_ngn` as const;

                return (
                    <div key={interval} className="grid gap-1.5">
                        <Label
                            htmlFor={`${plan.id}_${key}`}
                            className="text-xs"
                        >
                            {intervalLabels[interval]} (₦)
                        </Label>
                        <Input
                            id={`${plan.id}_${key}`}
                            type="number"
                            min="1"
                            value={form.data[key]}
                            onChange={(event) =>
                                form.setData(key, event.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            List {minorToMoney('NGN', plan.list[interval])}
                        </p>
                        <InputError message={form.errors[key]} />
                    </div>
                );
            })}
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Save
                </Button>
                {plan.custom && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            router.delete(action, { preserveScroll: true })
                        }
                    >
                        Use list price
                    </Button>
                )}
            </div>
        </form>
    );
}
