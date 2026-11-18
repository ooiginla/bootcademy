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
    description: string | null;
    monthly_ngn: number;
    quarterly_ngn: number;
    annual_ngn: number;
    active_player_limit: number;
    branch_limit: number | null;
    staff_account_limit: number | null;
    is_recommended: boolean;
    migration_support: string | null;
    support_level: string | null;
    flags: Record<string, boolean>;
    flag_labels: Record<string, string>;
    is_active: boolean;
};

function Field({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    min,
    required = false,
    placeholder,
}: {
    name: string;
    label: string;
    defaultValue: string | number | null;
    error?: string;
    type?: string;
    min?: string;
    required?: boolean;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                min={min}
                defaultValue={defaultValue ?? ''}
                required={required}
                placeholder={placeholder}
            />
            <InputError message={error} />
        </div>
    );
}

function Checkbox({
    name,
    label,
    defaultChecked,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
}) {
    return (
        <label className="flex items-center gap-3 text-sm">
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                className="size-4"
            />
            {label}
        </label>
    );
}

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
                <Form
                    action={`/platform/plans/${plan.id}`}
                    method="patch"
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <h1 className="text-xl font-semibold">
                                        Edit {plan.name}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        These are list prices in whole Naira. An
                                        academy with its own price, set under
                                        Platform academies, pays that instead.
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <Field
                                        name="name"
                                        label="Plan name"
                                        defaultValue={plan.name}
                                        error={errors.name}
                                        required
                                    />
                                    <Field
                                        name="description"
                                        label="Short description"
                                        defaultValue={plan.description}
                                        error={errors.description}
                                    />
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Field
                                            name="monthly_ngn"
                                            label="Monthly (₦)"
                                            type="number"
                                            min="0"
                                            defaultValue={plan.monthly_ngn}
                                            error={errors.monthly_ngn}
                                            required
                                        />
                                        <Field
                                            name="quarterly_ngn"
                                            label="Quarterly (₦)"
                                            type="number"
                                            min="0"
                                            defaultValue={plan.quarterly_ngn}
                                            error={errors.quarterly_ngn}
                                            required
                                        />
                                        <Field
                                            name="annual_ngn"
                                            label="Annual (₦)"
                                            type="number"
                                            min="0"
                                            defaultValue={plan.annual_ngn}
                                            error={errors.annual_ngn}
                                            required
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h2 className="font-semibold">Limits</h2>
                                    <p className="text-sm text-muted-foreground">
                                        Limits stop new usage only. Existing
                                        records over a limit are kept. Leave
                                        branches or staff blank for no limit.
                                    </p>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-3">
                                    <Field
                                        name="active_player_limit"
                                        label="Active players"
                                        type="number"
                                        min="1"
                                        defaultValue={plan.active_player_limit}
                                        error={errors.active_player_limit}
                                        required
                                    />
                                    <Field
                                        name="branch_limit"
                                        label="Active branches"
                                        type="number"
                                        min="1"
                                        defaultValue={plan.branch_limit}
                                        error={errors.branch_limit}
                                        placeholder="No limit"
                                    />
                                    <Field
                                        name="staff_account_limit"
                                        label="Staff accounts"
                                        type="number"
                                        min="1"
                                        defaultValue={plan.staff_account_limit}
                                        error={errors.staff_account_limit}
                                        placeholder="No limit"
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h2 className="font-semibold">
                                        Features and support
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Every other feature is included in all
                                        plans.
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="space-y-3">
                                        {Object.entries(plan.flag_labels).map(
                                            ([key, label]) => (
                                                <Checkbox
                                                    key={key}
                                                    name={`flags[${key}]`}
                                                    label={label}
                                                    defaultChecked={
                                                        plan.flags[key] === true
                                                    }
                                                />
                                            ),
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            name="migration_support"
                                            label="Data migration"
                                            defaultValue={
                                                plan.migration_support
                                            }
                                            error={errors.migration_support}
                                            placeholder="e.g. Guided"
                                        />
                                        <Field
                                            name="support_level"
                                            label="Support"
                                            defaultValue={plan.support_level}
                                            error={errors.support_level}
                                            placeholder="e.g. Priority email"
                                        />
                                    </div>
                                    <Checkbox
                                        name="is_recommended"
                                        label="Highlight as the most popular plan"
                                        defaultChecked={plan.is_recommended}
                                    />
                                    <Checkbox
                                        name="is_active"
                                        label="Available for academies to choose"
                                        defaultChecked={plan.is_active}
                                    />
                                </CardContent>
                            </Card>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}Save plan
                            </Button>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}
