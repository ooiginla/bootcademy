import { Head, useForm } from '@inertiajs/react';
import { CreditCard, Percent, Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';
import BankDetailsCard from '@/components/bank-details-card';
import type { BankDetails, Settlement } from '@/components/bank-details-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Settings = {
    paystack_percentage: string;
    paystack_fixed_fee: string;
    paystack_cap: string;
    paystack_waiver_threshold: string;
    platform_percentage: string;
    platform_fixed_fee: string;
};

type Field = [keyof Settings, string, string];

const paystackFields: Field[] = [
    ['paystack_percentage', 'Percentage (%)', 'e.g. 1.50'],
    ['paystack_fixed_fee', 'Fixed fee (₦)', 'e.g. 100.00'],
    ['paystack_cap', 'Maximum fee (₦)', 'Leave blank for no cap'],
    [
        'paystack_waiver_threshold',
        'No fixed fee below (₦)',
        'Leave blank to always charge it',
    ],
];
const platformFields: Field[] = [
    ['platform_percentage', 'Percentage (%)', 'e.g. 1.00'],
    ['platform_fixed_fee', 'Fixed fee (₦)', 'e.g. 50.00'],
];

type TrialSettings = {
    trial_plan_id: number | null;
    post_trial_plan_id: number | null;
    trial_days: number;
};

export default function PlatformSettings({
    settings,
    paystack_configured,
    trial,
    plans,
    bank_details,
    settlement,
    banks,
}: {
    settings: Settings;
    paystack_configured: boolean;
    trial: TrialSettings;
    plans: Array<{ id: number; name: string }>;
    bank_details: BankDetails;
    settlement: Settlement;
    banks: Array<{ code: string; name: string }>;
}) {
    const form = useForm(settings);
    const trialForm = useForm({
        trial_plan_id: String(trial.trial_plan_id ?? ''),
        post_trial_plan_id: String(trial.post_trial_plan_id ?? ''),
        trial_days: String(trial.trial_days),
    });

    function submitTrial(event: FormEvent) {
        event.preventDefault();
        trialForm.patch('/platform/settings/trial', { preserveScroll: true });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch('/platform/settings', { preserveScroll: true });
    }

    const fields = (items: Field[]) =>
        items.map(([key, label, placeholder]) => (
            <div key={key} className="grid gap-2">
                <Label htmlFor={key}>{label}</Label>
                <Input
                    id={key}
                    inputMode="decimal"
                    value={form.data[key]}
                    placeholder={placeholder}
                    onChange={(event) => form.setData(key, event.target.value)}
                />
                <InputError message={form.errors[key]} />
            </div>
        ));

    return (
        <>
            <Head title="Platform settings" />
            <main className="mx-auto w-full max-w-4xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">Platform</p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Online payment fees
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Parents pay the academy’s amount plus an online payment
                        fee. The academy’s Paystack subaccount receives exactly
                        its amount. The fee goes to your main Paystack account,
                        Paystack takes its charge from it, and the rest is your
                        platform fee.
                    </p>
                    {!paystack_configured && (
                        <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            Paystack keys are not set on this server, so online
                            payments are off.
                        </p>
                    )}
                </div>
                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <h2 className="flex items-center gap-2 font-semibold">
                                <CreditCard className="size-5 text-primary" />
                                Paystack’s charge
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Match your Paystack pricing. Local Nigerian
                                cards are currently 1.5% + ₦100, with the ₦100
                                waived under ₦2,500 and the fee capped at
                                ₦2,000. Cards that cost more, such as
                                international cards, are paid from your platform
                                fee.
                            </p>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            {fields(paystackFields)}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Percent className="size-5 text-primary" />
                                Your platform fee
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Added on top of the academy’s amount, as a
                                percentage, a fixed amount, or both. Use 0 for
                                none. You can override it for one academy under
                                Platform academies.
                            </p>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            {fields(platformFields)}
                        </CardContent>
                    </Card>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        {form.processing ? 'Saving…' : 'Save fees'}
                    </Button>
                </form>

                <div className="mt-10 space-y-6">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            Academy subscriptions
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Academies pay their plan price plus the online
                            payment fee above. The plan price settles to your
                            account below.
                        </p>
                    </div>
                    {paystack_configured ? (
                        <BankDetailsCard
                            academyName="Your business"
                            bankDetails={bank_details}
                            settlement={settlement}
                            usesPaystack
                            banks={banks}
                            action="/platform/settings/settlement-account"
                            title="Subscription settlement account"
                            description="Subscription payments from academies settle to this account. The account name is confirmed with the bank when you save."
                            settlementText={{
                                active: 'Subscription payments settle straight to this account. The online payment fee stays in your main Paystack balance.',
                                inactive:
                                    'Subscription payments stay in your main Paystack balance until Paystack accepts this account.',
                            }}
                        />
                    ) : (
                        <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            Set the Paystack keys on this server to add a
                            settlement account and take subscription payments.
                        </p>
                    )}
                    <Card>
                        <CardHeader>
                            <h3 className="flex items-center gap-2 font-semibold">
                                <Sparkles className="size-5 text-primary" />
                                Free trial
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                New academies start on the trial plan. When the
                                trial ends without a payment they move to the
                                plan after the trial, and only see the
                                subscription page until they pay.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitTrial}
                                className="grid gap-4 sm:grid-cols-[1fr_1fr_8rem_auto] sm:items-end"
                            >
                                {(
                                    [
                                        ['trial_plan_id', 'Trial plan'],
                                        [
                                            'post_trial_plan_id',
                                            'Plan after the trial',
                                        ],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key} className="grid gap-2">
                                        <Label htmlFor={key}>{label}</Label>
                                        <select
                                            id={key}
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                            value={trialForm.data[key]}
                                            onChange={(event) =>
                                                trialForm.setData(
                                                    key,
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Select plan
                                            </option>
                                            {plans.map((plan) => (
                                                <option
                                                    key={plan.id}
                                                    value={plan.id}
                                                >
                                                    {plan.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={trialForm.errors[key]}
                                        />
                                    </div>
                                ))}
                                <div className="grid gap-2">
                                    <Label htmlFor="trial_days">Days</Label>
                                    <Input
                                        id="trial_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        value={trialForm.data.trial_days}
                                        onChange={(event) =>
                                            trialForm.setData(
                                                'trial_days',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={trialForm.errors.trial_days}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={
                                        trialForm.processing ||
                                        !trialForm.isDirty
                                    }
                                >
                                    Save
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
