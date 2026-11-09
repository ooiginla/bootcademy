import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, CreditCard, Palette } from 'lucide-react';
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
        paystack_fees: {
            percentage: string;
            fixed_fee: string;
            cap: string;
            waiver_threshold: string;
        };
    };
};

export default function PlatformAcademyBranding({ managedAcademy }: Props) {
    const form = useForm({
        primary_color: managedAcademy.theme.primary,
        secondary_color: managedAcademy.theme.secondary,
    });
    const feeForm = useForm(managedAcademy.paystack_fees);

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

                <Card className="mt-6">
                    <CardHeader>
                        <h2 className="flex items-center gap-2 font-semibold">
                            <CreditCard className="size-5 text-primary" />{' '}
                            Paystack customer fee
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Added to online checkout so the academy receives its
                            intended fee. Defaults reflect Paystack’s current
                            Nigerian local pricing.
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
                                        'percentage',
                                        'Percentage (%)',
                                        'e.g. 1.50',
                                    ],
                                    [
                                        'fixed_fee',
                                        'Fixed fee (₦)',
                                        'e.g. 100.00',
                                    ],
                                    [
                                        'cap',
                                        'Maximum fee (₦)',
                                        'Leave blank for no cap',
                                    ],
                                    [
                                        'waiver_threshold',
                                        'Fixed-fee waiver below (₦)',
                                        'e.g. 2500.00',
                                    ],
                                ] as const
                            ).map(([key, label, placeholder]) => (
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={`paystack-${key}`}>
                                        {label}
                                    </Label>
                                    <Input
                                        id={`paystack-${key}`}
                                        inputMode="decimal"
                                        value={feeForm.data[key]}
                                        placeholder={placeholder}
                                        onChange={(event) =>
                                            feeForm.setData(
                                                key,
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={feeForm.errors[key]} />
                                </div>
                            ))}
                            <div className="sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        feeForm.processing || !feeForm.isDirty
                                    }
                                >
                                    {feeForm.processing
                                        ? 'Saving…'
                                        : 'Save Paystack fees'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

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
