import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ReceiptText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Plan = {
    id: number;
    code: string;
    name: string;
    currency: string;
    monthly_ngn: number;
    quarterly_ngn: number;
    annual_ngn: number;
    active_player_limit: number;
    branch_limit: number | null;
    staff_account_limit: number | null;
    is_recommended: boolean;
    is_active: boolean;
    subscriptions_count: number;
};
const ngn = (amount: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        currencyDisplay: 'narrowSymbol',
        maximumFractionDigits: 0,
    }).format(amount);

export default function PlatformPlans({ plans }: { plans: Plan[] }) {
    return (
        <>
            <Head title="Platform plans" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Platform control plane
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold">
                        Plans and entitlements
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        List prices and limits academies see on their
                        subscription page. Every feature is included in every
                        plan except the ones you switch on per plan.
                    </p>
                </div>
                <div className="grid gap-4 lg:grid-cols-3">
                    {plans.map((plan) => (
                        <Card key={plan.id}>
                            <CardContent className="p-5">
                                <div className="flex items-start justify-between">
                                    <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <ReceiptText className="size-5" />
                                    </span>
                                    <div className="flex gap-2">
                                        {plan.is_recommended && (
                                            <Badge variant="outline">
                                                Most popular
                                            </Badge>
                                        )}
                                        <Badge
                                            variant={
                                                plan.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {plan.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>
                                <h2 className="mt-5 text-xl font-semibold">
                                    {plan.name}
                                </h2>
                                <p className="mt-2 text-3xl font-semibold">
                                    {ngn(plan.monthly_ngn)}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {' '}
                                        / month
                                    </span>
                                </p>
                                <div className="mt-5 space-y-2 text-sm text-muted-foreground">
                                    <p>
                                        {plan.active_player_limit.toLocaleString()}{' '}
                                        active players
                                    </p>
                                    <p>
                                        {plan.branch_limit ?? 'Unlimited'}{' '}
                                        branches ·{' '}
                                        {plan.staff_account_limit ??
                                            'Unlimited'}{' '}
                                        staff accounts
                                    </p>
                                    <p>
                                        {plan.subscriptions_count} academies on
                                        this plan
                                    </p>
                                    <p>Quarterly {ngn(plan.quarterly_ngn)}</p>
                                    <p>Annual {ngn(plan.annual_ngn)}</p>
                                </div>
                                <Button
                                    variant="outline"
                                    className="mt-6 w-full justify-between"
                                    asChild
                                >
                                    <Link
                                        href={`/platform/plans/${plan.id}/edit`}
                                    >
                                        Edit plan
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
        </>
    );
}
