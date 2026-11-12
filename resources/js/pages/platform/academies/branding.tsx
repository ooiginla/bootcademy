import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CreditCard,
    Landmark,
    Palette,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AcademyTheme } from '@/types/auth';

type Props = {
    managedAcademy: {
        id: number;
        name: string;
        theme: AcademyTheme;
        platform_fee: {
            platform_percentage: string;
            platform_fixed_fee: string;
        };
        default_platform_fee: { percentage: string; fixed_fee: string };
        settlement: {
            bank_name: string | null;
            account_name: string | null;
            account_number_last4: string | null;
            status: string;
            subaccount_code: string | null;
            error: string | null;
            synced_at: string | null;
            can_retry: boolean;
        };
    };
};

export default function PlatformAcademyBranding({ managedAcademy }: Props) {
    const form = useForm({
        primary_color: managedAcademy.theme.primary,
        secondary_color: managedAcademy.theme.secondary,
    });
    const feeForm = useForm(managedAcademy.platform_fee);
    const syncForm = useForm({});
    const settlement = managedAcademy.settlement;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(`/platform/academies/${managedAcademy.id}/branding`, {
            preserveScroll: true,
        });
    }

    function submitFees(event: FormEvent) {
        event.preventDefault();
        feeForm.patch(
            `/platform/academies/${managedAcademy.id}/paystack-fees`,
            { preserveScroll: true },
        );
    }

    function retrySubaccount() {
        syncForm.post(
            `/platform/academies/${managedAcademy.id}/paystack-subaccount`,
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`${managedAcademy.name} theme`} />
            <main className="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" className="mb-5 -ml-3" asChild>
                    <Link href="/platform/academies">
                        <ArrowLeft className="size-4" />
                        All academies
                    </Link>
                </Button>
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Platform theme controls
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        {managedAcademy.name}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Foreground colours are derived automatically to preserve
                        readable contrast.
                    </p>
                </div>

                <div className="mt-6 mb-6 grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <h2 className="flex items-center gap-2 font-semibold">
                                <Landmark className="size-5 text-primary" />
                                Paystack settlement account
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Online payments for this academy settle to this
                                subaccount. The academy sets it up by saving its
                                bank details.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <dl className="grid gap-2 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd className="font-medium capitalize">
                                        {settlement.status}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Subaccount code
                                    </dt>
                                    <dd className="font-mono text-xs">
                                        {settlement.subaccount_code ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Bank
                                    </dt>
                                    <dd>{settlement.bank_name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Account
                                    </dt>
                                    <dd>
                                        {settlement.account_name ?? '—'}
                                        {settlement.account_number_last4 &&
                                            ` · ••••${settlement.account_number_last4}`}
                                    </dd>
                                </div>
                            </dl>
                            {settlement.error && (
                                <p
                                    className="text-xs text-destructive"
                                    role="alert"
                                >
                                    {settlement.error}
                                </p>
                            )}
                            {settlement.can_retry && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={retrySubaccount}
                                    disabled={syncForm.processing}
                                >
                                    {syncForm.processing
                                        ? 'Contacting Paystack…'
                                        : 'Send to Paystack again'}
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h2 className="flex items-center gap-2 font-semibold">
                                <CreditCard className="size-5 text-primary" />
                                Platform fee for this academy
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Leave both blank to use the default (
                                {managedAcademy.default_platform_fee.percentage}
                                % + ₦
                                {managedAcademy.default_platform_fee.fixed_fee}
                                ). Paystack rates are set in{' '}
                                <Link
                                    href="/platform/settings"
                                    className="underline"
                                >
                                    Platform settings
                                </Link>
                                .
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitFees}
                                className="grid gap-5 sm:grid-cols-2"
                            >
                                {(
                                    [
                                        [
                                            'platform_percentage',
                                            'Percentage (%)',
                                        ],
                                        ['platform_fixed_fee', 'Fixed fee (₦)'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key} className="grid gap-2">
                                        <Label htmlFor={`platform-${key}`}>
                                            {label}
                                        </Label>
                                        <Input
                                            id={`platform-${key}`}
                                            inputMode="decimal"
                                            value={feeForm.data[key]}
                                            placeholder="Use default"
                                            onChange={(event) =>
                                                feeForm.setData(
                                                    key,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={feeForm.errors[key]}
                                        />
                                    </div>
                                ))}
                                <div className="sm:col-span-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            feeForm.processing ||
                                            !feeForm.isDirty
                                        }
                                    >
                                        {feeForm.processing
                                            ? 'Saving…'
                                            : 'Save platform fee'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
                    <Card>
                        <CardHeader>
                            <h2 className="font-semibold">Academy colours</h2>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="primary_color">
                                        Primary colour
                                    </Label>
                                    <div className="flex gap-3">
                                        <input
                                            id="primary_picker"
                                            aria-label="Choose primary colour"
                                            type="color"
                                            value={form.data.primary_color}
                                            onChange={(event) =>
                                                form.setData(
                                                    'primary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className="h-10 w-14 cursor-pointer rounded-md border bg-background p-1"
                                        />
                                        <Input
                                            id="primary_color"
                                            value={form.data.primary_color}
                                            onChange={(event) =>
                                                form.setData(
                                                    'primary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            maxLength={7}
                                        />
                                    </div>
                                    <InputError
                                        message={form.errors.primary_color}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="secondary_color">
                                        Secondary colour
                                    </Label>
                                    <div className="flex gap-3">
                                        <input
                                            id="secondary_picker"
                                            aria-label="Choose secondary colour"
                                            type="color"
                                            value={form.data.secondary_color}
                                            onChange={(event) =>
                                                form.setData(
                                                    'secondary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className="h-10 w-14 cursor-pointer rounded-md border bg-background p-1"
                                        />
                                        <Input
                                            id="secondary_color"
                                            value={form.data.secondary_color}
                                            onChange={(event) =>
                                                form.setData(
                                                    'secondary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            maxLength={7}
                                        />
                                    </div>
                                    <InputError
                                        message={form.errors.secondary_color}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    {form.processing
                                        ? 'Saving…'
                                        : 'Save academy theme'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden">
                        <div
                            className="p-6"
                            style={{
                                backgroundColor: form.data.primary_color,
                                color: managedAcademy.theme.primary_foreground,
                            }}
                        >
                            <Palette className="size-6" aria-hidden="true" />
                            <h2 className="mt-6 text-xl font-semibold">
                                Theme preview
                            </h2>
                            <p className="mt-2 text-sm opacity-80">
                                Navigation and primary actions respond to this
                                academy's identity.
                            </p>
                        </div>
                        <CardContent className="p-6">
                            <div
                                className="rounded-xl p-4"
                                style={{
                                    backgroundColor: form.data.secondary_color,
                                    color: managedAcademy.theme
                                        .secondary_foreground,
                                }}
                            >
                                <p className="font-medium">Secondary surface</p>
                                <p className="mt-1 text-sm opacity-75">
                                    Used for supporting emphasis and selected
                                    states.
                                </p>
                            </div>
                            <p className="mt-5 flex items-center gap-2 text-xs text-muted-foreground">
                                <CheckCircle2
                                    className="size-4 text-emerald-600"
                                    aria-hidden="true"
                                />
                                Destructive and warning colours are never
                                overridden.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
