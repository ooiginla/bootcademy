import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Plan = {
    id: number;
    code: string;
    name: string;
    monthly_ngn: number;
    quarterly_ngn: number;
    annual_ngn: number;
    active_player_limit: number;
    is_active: boolean;
};
export default function EditPlan({ plan }: { plan: Plan }) {
    return (
        <>
            <Head title={`Edit ${plan.name}`} />
            <main className="mx-auto w-full max-w-3xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" className="mb-5 -ml-3" asChild>
                    <Link href="/platform/plans">
                        <ArrowLeft className="size-4" />
                        All plans
                    </Link>
                </Button>
                <Card>
                    <CardHeader>
                        <h1 className="text-xl font-semibold">
                            Edit {plan.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Prices below are whole Naira and stored as integer
                            kobo.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/platform/plans/${plan.id}`}
                            method="patch"
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Plan name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={plan.name}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        {[
                                            [
                                                'monthly_ngn',
                                                'Monthly (₦)',
                                                plan.monthly_ngn,
                                            ],
                                            [
                                                'quarterly_ngn',
                                                'Quarterly (₦)',
                                                plan.quarterly_ngn,
                                            ],
                                            [
                                                'annual_ngn',
                                                'Annual (₦)',
                                                plan.annual_ngn,
                                            ],
                                        ].map(([name, label, value]) => (
                                            <div
                                                className="grid gap-2"
                                                key={String(name)}
                                            >
                                                <Label htmlFor={String(name)}>
                                                    {label}
                                                </Label>
                                                <Input
                                                    id={String(name)}
                                                    name={String(name)}
                                                    type="number"
                                                    min="0"
                                                    defaultValue={Number(value)}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[String(name)]
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="active_player_limit">
                                            Active learner limit
                                        </Label>
                                        <Input
                                            id="active_player_limit"
                                            name="active_player_limit"
                                            type="number"
                                            min="1"
                                            defaultValue={
                                                plan.active_player_limit
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.active_player_limit}
                                        />
                                    </div>
                                    <label className="flex items-center gap-3 text-sm">
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={plan.is_active}
                                            className="size-4"
                                        />
                                        Available for assignment
                                    </label>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}Save plan
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
