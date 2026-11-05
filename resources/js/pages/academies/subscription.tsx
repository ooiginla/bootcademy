import { Head } from '@inertiajs/react';
import { CircleAlert, Gauge, ReceiptText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';

type Subscription = {
    status: string;
    status_label: string;
    starts_at: string;
    plan: {
        name: string;
        currency: string;
        monthly_minor: number;
        quarterly_minor: number;
        annual_minor: number;
        active_player_limit: number;
    };
};

function money(minor: number, currency: string): string {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
        maximumFractionDigits: 0,
    }).format(minor / 100);
}

export default function AcademySubscription({
    subscription,
}: {
    subscription: Subscription | null;
}) {
    const terminology = useAcademyTerminology();
    return (
        <>
            <Head title="Subscription" />
            <main className="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Academy settings
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Subscription and limits
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        SaaS subscription billing is separate from{' '}
                        {terminology.singularLower} fees collected by your
                        academy.
                    </p>
                </div>
                {!subscription ? (
                    <Card>
                        <CardContent className="flex flex-col items-center py-14 text-center">
                            <CircleAlert className="size-9 text-amber-600" />
                            <h2 className="mt-4 font-semibold">
                                No plan assigned
                            </h2>
                            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                A Platform Super Admin must assign a plan before
                                plan-governed operational modules are enabled.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <ReceiptText className="size-5" />
                                    </span>
                                    <Badge
                                        variant={
                                            subscription.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {subscription.status_label}
                                    </Badge>
                                </div>
                                <h2 className="mt-3 text-xl font-semibold">
                                    {subscription.plan.name}
                                </h2>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-semibold">
                                    {money(
                                        subscription.plan.monthly_minor,
                                        subscription.plan.currency,
                                    )}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {' '}
                                        / month
                                    </span>
                                </p>
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Quarterly{' '}
                                    {money(
                                        subscription.plan.quarterly_minor,
                                        subscription.plan.currency,
                                    )}{' '}
                                    · Annual{' '}
                                    {money(
                                        subscription.plan.annual_minor,
                                        subscription.plan.currency,
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <Gauge className="size-5" />
                                </span>
                                <h2 className="mt-3 font-semibold">
                                    Current entitlement
                                </h2>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-semibold tabular-nums">
                                    {subscription.plan.active_player_limit.toLocaleString()}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Active {terminology.singularLower} limit
                                </p>
                                <p className="mt-5 text-xs text-muted-foreground">
                                    Limits prevent prohibited new usage;
                                    changing plans never silently deletes
                                    academy data.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </main>
        </>
    );
}
