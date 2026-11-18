import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarDays, Pencil, Plus, TicketPercent } from 'lucide-react';
import { FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    formatCurrency,
    formatMoneyInput,
    normalizeMoneyInput,
} from '@/lib/money';

type CouponScope = 'application_fee' | 'programme_fee' | 'cycle_fees' | 'store';

type Coupon = {
    id: number;
    code: string;
    name: string;
    discount_type: 'percentage' | 'fixed';
    percentage: number | null;
    fixed_amount: string | null;
    currency: string;
    applies_to: CouponScope[];
    minimum_amount: string | null;
    starts_on: string | null;
    ends_on: string | null;
    maximum_redemptions: number | null;
    per_customer_limit: number | null;
    is_active: boolean;
    redemptions_count: number;
    redeemed_count: number;
};

type CouponForm = {
    code: string;
    name: string;
    discount_type: 'percentage' | 'fixed';
    percentage: string;
    fixed_amount: string;
    applies_to: CouponScope[];
    minimum_amount: string;
    starts_on: string;
    ends_on: string;
    maximum_redemptions: string;
    per_customer_limit: string;
    is_active: boolean;
};

const scopes: { value: CouponScope; label: string; detail: string }[] = [
    {
        value: 'application_fee',
        label: 'Application fee',
        detail: 'The fee charged when an application is submitted.',
    },
    {
        value: 'programme_fee',
        label: 'Programme fees',
        detail: 'Programme invoices and their instalments.',
    },
    {
        value: 'cycle_fees',
        label: 'Additional cycle fees',
        detail: 'Optional or required fees added to a cycle.',
    },
    {
        value: 'store',
        label: 'Online store',
        detail: 'Merchandise and other public store orders.',
    },
];

const scopeLabel = (scope: CouponScope) =>
    scopes.find((item) => item.value === scope)?.label ?? scope;

function CouponDialog({
    currency,
    coupon,
}: {
    currency: string;
    coupon?: Coupon;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<CouponForm>({
        code: coupon?.code ?? '',
        name: coupon?.name ?? '',
        discount_type: coupon?.discount_type ?? 'percentage',
        percentage: coupon?.percentage?.toString() ?? '',
        fixed_amount: coupon?.fixed_amount ?? '',
        applies_to: coupon?.applies_to ?? ['programme_fee'],
        minimum_amount: coupon?.minimum_amount ?? '',
        starts_on: coupon?.starts_on ?? '',
        ends_on: coupon?.ends_on ?? '',
        maximum_redemptions: coupon?.maximum_redemptions?.toString() ?? '',
        per_customer_limit: coupon?.per_customer_limit?.toString() ?? '1',
        is_active: coupon?.is_active ?? true,
    });

    const toggleScope = (scope: CouponScope) => {
        form.setData(
            'applies_to',
            form.data.applies_to.includes(scope)
                ? form.data.applies_to.filter((item) => item !== scope)
                : [...form.data.applies_to, scope],
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!coupon) form.reset();
            },
        };
        if (coupon) form.patch(`/app/coupons/${coupon.id}`, options);
        else form.post('/app/coupons', options);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={coupon ? 'outline' : 'default'}
                    size={coupon ? 'sm' : 'default'}
                >
                    {coupon ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {coupon ? 'Edit' : 'Create coupon'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {coupon ? 'Edit coupon' : 'Create coupon'}
                    </DialogTitle>
                    <DialogDescription>
                        Discounts are validated by the server immediately before
                        checkout. Deactivate a coupon to stop future use without
                        removing its history.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-code-${coupon?.id ?? 'new'}`}
                            >
                                Coupon code
                            </Label>
                            <Input
                                id={`coupon-code-${coupon?.id ?? 'new'}`}
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="WELCOME10"
                                required
                            />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-name-${coupon?.id ?? 'new'}`}
                            >
                                Internal name
                            </Label>
                            <Input
                                id={`coupon-name-${coupon?.id ?? 'new'}`}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Welcome discount"
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-type-${coupon?.id ?? 'new'}`}
                            >
                                Discount type
                            </Label>
                            <select
                                id={`coupon-type-${coupon?.id ?? 'new'}`}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.discount_type}
                                onChange={(event) =>
                                    form.setData(
                                        'discount_type',
                                        event.target
                                            .value as CouponForm['discount_type'],
                                    )
                                }
                            >
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed amount</option>
                            </select>
                        </div>
                        {form.data.discount_type === 'percentage' ? (
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`coupon-percentage-${coupon?.id ?? 'new'}`}
                                >
                                    Percentage off
                                </Label>
                                <div className="relative">
                                    <Input
                                        id={`coupon-percentage-${coupon?.id ?? 'new'}`}
                                        type="number"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        value={form.data.percentage}
                                        onChange={(event) =>
                                            form.setData(
                                                'percentage',
                                                event.target.value,
                                            )
                                        }
                                        className="pr-9"
                                        required
                                    />
                                    <span className="absolute top-2 right-3 text-sm text-muted-foreground">
                                        %
                                    </span>
                                </div>
                                <InputError message={form.errors.percentage} />
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`coupon-fixed-${coupon?.id ?? 'new'}`}
                                >
                                    Amount off ({currency})
                                </Label>
                                <Input
                                    id={`coupon-fixed-${coupon?.id ?? 'new'}`}
                                    inputMode="decimal"
                                    value={formatMoneyInput(
                                        form.data.fixed_amount,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'fixed_amount',
                                            normalizeMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.fixed_amount}
                                />
                            </div>
                        )}
                    </div>

                    <fieldset className="grid gap-3">
                        <legend className="text-sm font-medium">
                            Where this coupon applies
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {scopes.map((scope) => (
                                <label
                                    key={scope.value}
                                    className="flex cursor-pointer gap-3 rounded-lg border p-3"
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4"
                                        checked={form.data.applies_to.includes(
                                            scope.value,
                                        )}
                                        onChange={() =>
                                            toggleScope(scope.value)
                                        }
                                    />
                                    <span>
                                        <span className="block text-sm font-medium">
                                            {scope.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {scope.detail}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.applies_to} />
                    </fieldset>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-minimum-${coupon?.id ?? 'new'}`}
                            >
                                Minimum spend ({currency})
                            </Label>
                            <Input
                                id={`coupon-minimum-${coupon?.id ?? 'new'}`}
                                inputMode="decimal"
                                value={formatMoneyInput(
                                    form.data.minimum_amount,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'minimum_amount',
                                        normalizeMoneyInput(event.target.value),
                                    )
                                }
                                placeholder="Optional"
                            />
                            <InputError message={form.errors.minimum_amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-start-${coupon?.id ?? 'new'}`}
                            >
                                Starts on
                            </Label>
                            <Input
                                id={`coupon-start-${coupon?.id ?? 'new'}`}
                                type="date"
                                value={form.data.starts_on}
                                onChange={(event) =>
                                    form.setData(
                                        'starts_on',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-end-${coupon?.id ?? 'new'}`}
                            >
                                Ends on
                            </Label>
                            <Input
                                id={`coupon-end-${coupon?.id ?? 'new'}`}
                                type="date"
                                value={form.data.ends_on}
                                onChange={(event) =>
                                    form.setData('ends_on', event.target.value)
                                }
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-max-${coupon?.id ?? 'new'}`}
                            >
                                Maximum total uses
                            </Label>
                            <Input
                                id={`coupon-max-${coupon?.id ?? 'new'}`}
                                type="number"
                                min="1"
                                value={form.data.maximum_redemptions}
                                onChange={(event) =>
                                    form.setData(
                                        'maximum_redemptions',
                                        event.target.value,
                                    )
                                }
                                placeholder="Unlimited"
                            />
                            <InputError
                                message={form.errors.maximum_redemptions}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`coupon-person-${coupon?.id ?? 'new'}`}
                            >
                                Uses per email
                            </Label>
                            <Input
                                id={`coupon-person-${coupon?.id ?? 'new'}`}
                                type="number"
                                min="1"
                                value={form.data.per_customer_limit}
                                onChange={(event) =>
                                    form.setData(
                                        'per_customer_limit',
                                        event.target.value,
                                    )
                                }
                                placeholder="Unlimited"
                            />
                            <InputError
                                message={form.errors.per_customer_limit}
                            />
                        </div>
                    </div>

                    <label className="flex items-center gap-3 rounded-lg border p-3">
                        <input
                            type="checkbox"
                            className="size-4"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />
                        <span>
                            <span className="block text-sm font-medium">
                                Coupon is active
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Date and usage limits are still enforced.
                            </span>
                        </span>
                    </label>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {coupon ? 'Save changes' : 'Create coupon'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Coupons({
    currency,
    coupons,
}: {
    currency: string;
    coupons: Coupon[];
}) {
    return (
        <>
            <Head title="Coupons" />
            <main className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Coupons & discounts
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Offer controlled discounts across fees and store
                            checkout without changing the original price.
                        </p>
                    </div>
                    <CouponDialog currency={currency} />
                </div>

                <div className="flex gap-2 border-b">
                    <Button asChild variant="ghost" className="rounded-b-none">
                        <Link href="/app/scholarships">Scholarships</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        className="rounded-b-none"
                        disabled
                    >
                        Coupons
                    </Button>
                </div>

                {coupons.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <TicketPercent className="mx-auto size-10 text-muted-foreground" />
                            <h2 className="mt-4 font-semibold">
                                No coupons yet
                            </h2>
                            <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                                Create a coupon, choose where it applies, and
                                share its code. Every use is retained for audit.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {coupons.map((coupon) => (
                            <Card key={coupon.id}>
                                <CardContent className="space-y-4 pt-6">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <code className="rounded bg-muted px-2 py-1 text-sm font-bold">
                                                    {coupon.code}
                                                </code>
                                                <Badge
                                                    variant={
                                                        coupon.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {coupon.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </div>
                                            <h2 className="mt-2 font-semibold">
                                                {coupon.name}
                                            </h2>
                                        </div>
                                        <CouponDialog
                                            currency={currency}
                                            coupon={coupon}
                                        />
                                    </div>

                                    <div className="text-2xl font-black">
                                        {coupon.discount_type === 'percentage'
                                            ? `${coupon.percentage}% off`
                                            : `${formatCurrency(currency, coupon.fixed_amount ?? '0')} off`}
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {coupon.applies_to.map((scope) => (
                                            <Badge
                                                key={scope}
                                                variant="outline"
                                            >
                                                {scopeLabel(scope)}
                                            </Badge>
                                        ))}
                                    </div>

                                    <div className="grid gap-2 border-t pt-4 text-sm sm:grid-cols-2">
                                        <p>
                                            <span className="text-muted-foreground">
                                                Uses:{' '}
                                            </span>
                                            {coupon.redemptions_count}
                                            {coupon.maximum_redemptions !== null
                                                ? ` / ${coupon.maximum_redemptions}`
                                                : ''}
                                        </p>
                                        <p>
                                            <span className="text-muted-foreground">
                                                Per email:{' '}
                                            </span>
                                            {coupon.per_customer_limit ??
                                                'Unlimited'}
                                        </p>
                                        <p>
                                            <span className="text-muted-foreground">
                                                Minimum:{' '}
                                            </span>
                                            {coupon.minimum_amount === null
                                                ? 'None'
                                                : formatCurrency(
                                                      currency,
                                                      coupon.minimum_amount,
                                                  )}
                                        </p>
                                        <p className="flex items-center gap-1">
                                            <CalendarDays className="size-4 text-muted-foreground" />
                                            {coupon.starts_on ?? 'Immediately'}{' '}
                                            – {coupon.ends_on ?? 'No end date'}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}
