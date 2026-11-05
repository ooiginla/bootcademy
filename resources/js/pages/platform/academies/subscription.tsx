import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    managedAcademy: { id: number; name: string };
    subscription: {
        plan_id: number;
        plan_name: string;
        status: string;
        starts_at: string;
    } | null;
    plans: {
        id: number;
        name: string;
        active_player_limit: number;
        monthly_ngn: number;
    }[];
    statuses: { value: string; label: string }[];
};
const ngn = (value: number) =>
    new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        currencyDisplay: 'narrowSymbol',
        maximumFractionDigits: 0,
    }).format(value);
export default function AcademySubscription({
    managedAcademy,
    subscription,
    plans,
    statuses,
}: Props) {
    return (
        <>
            <Head title={`${managedAcademy.name} subscription`} />
            <main className="mx-auto w-full max-w-3xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" className="mb-5 -ml-3" asChild>
                    <Link href="/platform/academies">
                        <ArrowLeft className="size-4" />
                        All academies
                    </Link>
                </Button>
                <Card>
                    <CardHeader>
                        <p className="text-sm font-medium text-primary">
                            Platform subscription control
                        </p>
                        <h1 className="text-xl font-semibold">
                            {managedAcademy.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Administrative assignment only. This does not charge
                            a payment method.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/platform/academies/${managedAcademy.id}/subscription`}
                            method="patch"
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan_id">Plan</Label>
                                        <select
                                            id="plan_id"
                                            name="plan_id"
                                            required
                                            defaultValue={
                                                subscription?.plan_id ?? ''
                                            }
                                            className="h-10 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="" disabled>
                                                Select a plan
                                            </option>
                                            {plans.map((plan) => (
                                                <option
                                                    key={plan.id}
                                                    value={plan.id}
                                                >
                                                    {plan.name} ·{' '}
                                                    {ngn(plan.monthly_ngn)}
                                                    /month ·{' '}
                                                    {
                                                        plan.active_player_limit
                                                    }{' '}
                                                    learners
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.plan_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <select
                                            id="status"
                                            name="status"
                                            required
                                            defaultValue={
                                                subscription?.status ?? 'active'
                                            }
                                            className="h-10 rounded-md border bg-background px-3 text-sm"
                                        >
                                            {statuses.map((status) => (
                                                <option
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {subscription
                                            ? 'Update subscription'
                                            : 'Assign subscription'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
