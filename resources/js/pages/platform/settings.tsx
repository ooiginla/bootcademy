import { Head, useForm } from '@inertiajs/react';
import { CreditCard, Percent } from 'lucide-react';
import type { FormEvent } from 'react';
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

export default function PlatformSettings({
    settings,
    paystack_configured,
}: {
    settings: Settings;
    paystack_configured: boolean;
}) {
    const form = useForm(settings);

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
            </main>
        </>
    );
}
