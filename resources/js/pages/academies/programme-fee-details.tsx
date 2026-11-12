import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import DeleteRecordButton from '@/components/delete-record-button';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
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
    sumMoney,
} from '@/lib/money';

type MoneyRow = { name: string; description?: string | null; amount: string };
type InstallmentRow = MoneyRow & {
    due_on: string;
    due_offset_days: string;
};
type PaymentPlan = {
    id: number;
    name: string;
    description: string | null;
    sort_order?: number;
    currency: string;
    total: string;
    is_default: boolean;
    is_active: boolean;
    is_legacy: boolean;
    is_standard?: boolean;
    items: MoneyRow[];
    installments: InstallmentRow[];
};
type Props = {
    offering: {
        id: number;
        fee_name: string;
        programme_name: string;
        branch_name: string | null;
        currency: string;
        is_active: boolean;
    };
    cycle: {
        id: number;
        name: string;
        type: 'fixed' | 'rolling';
        starts_on: string | null;
        ends_on: string | null;
        duration_value: number | null;
        duration_unit: string | null;
    };
    scope: {
        academy_cycle_term_id: number | null;
        term_name: string | null;
        starts_on: string | null;
        ends_on: string | null;
        cohort_id: number | null;
        cohort_name: string | null;
    };
    base_fee_items: MoneyRow[];
    plans: PaymentPlan[];
};

function PaymentPlanDialog({
    offering,
    cycle,
    scope,
    baseFeeItems,
    plan,
    nextSortOrder = 100,
}: Pick<Props, 'offering' | 'cycle' | 'scope'> & {
    baseFeeItems: MoneyRow[];
    plan?: PaymentPlan;
    nextSortOrder?: number;
}) {
    const [open, setOpen] = useState(false);
    const baseTotal = sumMoney(baseFeeItems.map((item) => item.amount));
    const form = useForm({
        name: plan?.name ?? '',
        description: plan?.description ?? '',
        sort_order: plan?.sort_order ?? nextSortOrder,
        academy_cycle_term_id: scope.academy_cycle_term_id,
        cohort_id: scope.cohort_id,
        is_default: plan?.is_default ?? false,
        is_active: plan?.is_active ?? true,
        fee_items:
            plan?.items.map((item) => ({ ...item })) ??
            baseFeeItems.map((item) => ({ ...item })),
        installments: plan?.installments.map((item) => ({
            ...item,
            due_on: item.due_on ?? '',
            due_offset_days:
                item.due_offset_days === null ||
                item.due_offset_days === undefined
                    ? ''
                    : String(item.due_offset_days),
        })) ?? [
            {
                name: 'Full payment',
                amount: baseTotal,
                due_on: '',
                due_offset_days: '',
            },
        ],
    });
    const feeTotal = sumMoney(form.data.fee_items.map((item) => item.amount));
    const repaymentTotal = sumMoney(
        form.data.installments.map((item) => item.amount),
    );
    const balanced = feeTotal === repaymentTotal;

    function submit(event: FormEvent) {
        event.preventDefault();
        const url = `/app/finance/cycles/${cycle.id}/programmes/${offering.id}/payment-plans${plan ? `/${plan.id}` : ''}`;
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (plan) form.patch(url, options);
        else form.post(url, options);
    }

    function updateFeeItem(index: number, key: keyof MoneyRow, value: string) {
        form.setData(
            'fee_items',
            form.data.fee_items.map((item, row) =>
                row === index ? { ...item, [key]: value } : item,
            ),
        );
    }

    function updateInstallment(
        index: number,
        key: keyof InstallmentRow,
        value: string,
    ) {
        form.setData(
            'installments',
            form.data.installments.map((item, row) =>
                row === index ? { ...item, [key]: value } : item,
            ),
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {plan ? (
                    <Button variant="outline" size="sm">
                        <Pencil className="size-4" /> Edit plan
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" /> New payment plan
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {plan ? 'Edit' : 'Create'} payment plan
                    </DialogTitle>
                    <DialogDescription>
                        Define what makes up the contracted total, then split
                        that exact amount into repayments.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="payment-plan-name">Plan name</Label>
                            <Input
                                id="payment-plan-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Monthly · 12 payments"
                                maxLength={120}
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment-plan-description">
                                Short description
                            </Label>
                            <Input
                                id="payment-plan-description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="For families paying monthly"
                                maxLength={500}
                            />
                            <InputError message={form.errors.description} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment-plan-order">
                                Display order
                            </Label>
                            <Input
                                id="payment-plan-order"
                                type="number"
                                min={0}
                                max={65535}
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.sort_order} />
                        </div>
                    </div>

                    <fieldset className="space-y-3">
                        <div>
                            <legend className="font-semibold">
                                Fee breakdown
                            </legend>
                            <p className="text-sm text-muted-foreground">
                                Add transparent adjustments when this plan costs
                                more or less than the standard fee.
                            </p>
                        </div>
                        {form.data.fee_items.map((item, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_1.5fr_12rem_auto]"
                            >
                                <Input
                                    aria-label={`Fee item ${index + 1} name`}
                                    value={item.name}
                                    onChange={(event) =>
                                        updateFeeItem(
                                            index,
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Programme fee"
                                    required
                                />
                                <Input
                                    aria-label={`Fee item ${index + 1} description`}
                                    value={item.description ?? ''}
                                    onChange={(event) =>
                                        updateFeeItem(
                                            index,
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. $3/month"
                                    maxLength={500}
                                />
                                <Input
                                    aria-label={`Fee item ${index + 1} amount`}
                                    value={formatMoneyInput(item.amount)}
                                    onChange={(event) =>
                                        updateFeeItem(
                                            index,
                                            'amount',
                                            normalizeMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="decimal"
                                    required
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove fee item ${index + 1}`}
                                    disabled={form.data.fee_items.length === 1}
                                    onClick={() =>
                                        form.setData(
                                            'fee_items',
                                            form.data.fee_items.filter(
                                                (_, row) => row !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                                <InputError
                                    message={
                                        form.errors[
                                            `fee_items.${index}.description`
                                        ]
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors[`fee_items.${index}.amount`]
                                    }
                                />
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('fee_items', [
                                    ...form.data.fee_items,
                                    { name: '', description: '', amount: '' },
                                ])
                            }
                        >
                            <Plus className="size-4" /> Add fee item
                        </Button>
                    </fieldset>

                    <fieldset className="space-y-3">
                        <div>
                            <legend className="font-semibold">
                                Repayment schedule
                            </legend>
                            <p className="text-sm text-muted-foreground">
                                {plan?.is_standard
                                    ? 'The standard plan is always collected as one full payment.'
                                    : cycle.type === 'fixed'
                                      ? 'Set the amount and due date for every repayment.'
                                      : 'Set each amount and the number of days after activation when it becomes due.'}
                            </p>
                        </div>
                        {form.data.installments.map((item, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2 lg:grid-cols-[1fr_10rem_12rem_auto]"
                            >
                                <Input
                                    aria-label={`Repayment ${index + 1} name`}
                                    value={item.name}
                                    onChange={(event) =>
                                        updateInstallment(
                                            index,
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={`Payment ${index + 1}`}
                                    required
                                />
                                <Input
                                    aria-label={`Repayment ${index + 1} amount`}
                                    value={formatMoneyInput(item.amount)}
                                    onChange={(event) =>
                                        updateInstallment(
                                            index,
                                            'amount',
                                            normalizeMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="decimal"
                                    required
                                />
                                {cycle.type === 'fixed' ? (
                                    <Input
                                        aria-label={`Repayment ${index + 1} due date`}
                                        type="date"
                                        min={
                                            scope.starts_on ??
                                            cycle.starts_on ??
                                            undefined
                                        }
                                        max={
                                            scope.ends_on ??
                                            cycle.ends_on ??
                                            undefined
                                        }
                                        value={item.due_on}
                                        onChange={(event) =>
                                            updateInstallment(
                                                index,
                                                'due_on',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                ) : (
                                    <Input
                                        aria-label={`Repayment ${index + 1} days after activation`}
                                        type="number"
                                        min={0}
                                        value={item.due_offset_days}
                                        onChange={(event) =>
                                            updateInstallment(
                                                index,
                                                'due_offset_days',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Days after activation"
                                        required
                                    />
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove repayment ${index + 1}`}
                                    disabled={
                                        form.data.installments.length === 1
                                    }
                                    onClick={() =>
                                        form.setData(
                                            'installments',
                                            form.data.installments.filter(
                                                (_, row) => row !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                                <InputError
                                    message={
                                        form.errors[
                                            `installments.${index}.${cycle.type === 'fixed' ? 'due_on' : 'due_offset_days'}`
                                        ]
                                    }
                                />
                            </div>
                        ))}
                        {!plan?.is_standard && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    form.setData('installments', [
                                        ...form.data.installments,
                                        {
                                            name: `Payment ${form.data.installments.length + 1}`,
                                            amount: '',
                                            due_on: '',
                                            due_offset_days: '',
                                        },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Add repayment
                            </Button>
                        )}
                        <InputError message={form.errors.installments} />
                    </fieldset>

                    <div className="grid gap-3 rounded-xl bg-muted/50 p-4 sm:grid-cols-3">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Contracted total
                            </p>
                            <p className="font-semibold">
                                {formatCurrency(offering.currency, feeTotal)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Repayments
                            </p>
                            <p className="font-semibold">
                                {form.data.installments.length} ·{' '}
                                {formatCurrency(
                                    offering.currency,
                                    repaymentTotal,
                                )}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Check
                            </p>
                            <p
                                className={
                                    balanced
                                        ? 'font-medium text-emerald-700'
                                        : 'font-medium text-destructive'
                                }
                            >
                                {balanced
                                    ? 'Totals match'
                                    : 'Repayment total must match'}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-5">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_default}
                                onChange={(event) =>
                                    form.setData(
                                        'is_default',
                                        event.target.checked,
                                    )
                                }
                            />
                            Default for this fee scope
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                            />
                            Available for selection
                        </label>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={form.processing || !balanced}
                        >
                            {form.processing && <Spinner />}
                            Save payment plan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ProgrammeFeeDetails({
    offering,
    cycle,
    scope,
    base_fee_items,
    plans,
}: Props) {
    const nextSortOrder =
        Math.max(0, ...plans.map((plan) => plan.sort_order ?? 0)) + 10;

    return (
        <>
            <Head title={`${offering.programme_name} payment plans`} />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <Button asChild variant="ghost" className="mb-4 -ml-3">
                    <Link href={`/app/finance/cycles/${cycle.id}`}>
                        <ArrowLeft className="size-4" /> Back to {cycle.name}
                    </Link>
                </Button>

                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            {offering.programme_name} ·{' '}
                            {offering.branch_name ?? 'All branches'}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            {offering.fee_name} payment plans
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {scope.term_name ?? 'Whole cycle'}
                            {' · '}
                            {scope.cohort_name ?? 'All cohorts'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <PaymentPlanDialog
                            offering={offering}
                            cycle={cycle}
                            scope={scope}
                            baseFeeItems={base_fee_items}
                            nextSortOrder={nextSortOrder}
                        />
                        <DeleteRecordButton
                            action={`/app/finance/cycles/${cycle.id}/programmes/${offering.id}`}
                            label="programme fee"
                            name={offering.fee_name}
                            description="Its standard payment plan is removed with it. Custom payment plans must be deleted first."
                        />
                    </div>
                </div>

                <div className="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">Payment plans</h2>
                        <p className="text-sm text-muted-foreground">
                            {plans.length} configured for this fee scope
                        </p>
                    </div>
                </div>

                {plans.length === 0 ? (
                    <Card>
                        <CardContent className="py-14 text-center">
                            <CalendarClock className="mx-auto size-9 text-muted-foreground" />
                            <h3 className="mt-4 font-semibold">
                                No payment plans yet
                            </h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Add a payment plan and define its fee items and
                                repayment schedule.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-5">
                        {plans.map((plan) => (
                            <Card key={plan.id}>
                                <CardHeader>
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="text-lg font-semibold">
                                                    {plan.name}
                                                </h3>
                                                {plan.is_default && (
                                                    <Badge>
                                                        <CheckCircle2 className="size-3" />
                                                        Default
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant={
                                                        plan.is_active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {plan.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                                {plan.is_legacy && (
                                                    <Badge variant="outline">
                                                        Existing schedule
                                                    </Badge>
                                                )}
                                                {plan.is_standard && (
                                                    <Badge variant="outline">
                                                        Standard one-off
                                                    </Badge>
                                                )}
                                            </div>
                                            {plan.description && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {plan.description}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <PaymentPlanDialog
                                                offering={offering}
                                                cycle={cycle}
                                                scope={scope}
                                                baseFeeItems={base_fee_items}
                                                plan={plan}
                                            />
                                            {!plan.is_standard && (
                                                <DeleteRecordButton
                                                    action={`/app/finance/cycles/${cycle.id}/programmes/${offering.id}/payment-plans/${plan.id}`}
                                                    label="payment plan"
                                                    name={plan.name}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="rounded-lg bg-muted/50 p-4">
                                            <p className="text-xs text-muted-foreground">
                                                Contracted total
                                            </p>
                                            <p className="mt-1 text-lg font-semibold">
                                                {formatCurrency(
                                                    plan.currency,
                                                    plan.total,
                                                )}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 p-4">
                                            <p className="text-xs text-muted-foreground">
                                                Number of repayments
                                            </p>
                                            <p className="mt-1 text-lg font-semibold">
                                                {plan.installments.length}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-muted/50 p-4">
                                            <p className="text-xs text-muted-foreground">
                                                Average repayment
                                            </p>
                                            <p className="mt-1 text-lg font-semibold">
                                                {formatCurrency(
                                                    plan.currency,
                                                    Number(plan.total) /
                                                        plan.installments
                                                            .length,
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid gap-5 lg:grid-cols-2">
                                        <div>
                                            <h4 className="mb-2 font-medium">
                                                Fee breakdown
                                            </h4>
                                            <div className="divide-y rounded-lg border">
                                                {plan.items.map(
                                                    (item, index) => (
                                                        <div
                                                            key={`${item.name}-${index}`}
                                                            className="flex justify-between gap-4 px-3 py-2 text-sm"
                                                        >
                                                            <span>
                                                                <span className="block">
                                                                    {item.name}
                                                                </span>
                                                                {item.description && (
                                                                    <span className="block text-xs text-muted-foreground">
                                                                        {
                                                                            item.description
                                                                        }
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <span className="font-medium">
                                                                {formatCurrency(
                                                                    plan.currency,
                                                                    item.amount,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <div>
                                            <h4 className="mb-2 font-medium">
                                                Repayment breakdown
                                            </h4>
                                            <div className="divide-y rounded-lg border">
                                                {plan.installments.map(
                                                    (installment, index) => (
                                                        <div
                                                            key={`${installment.name}-${index}`}
                                                            className="grid grid-cols-[1fr_auto] gap-x-4 px-3 py-2 text-sm"
                                                        >
                                                            <span>
                                                                {
                                                                    installment.name
                                                                }
                                                            </span>
                                                            <span className="font-medium">
                                                                {formatCurrency(
                                                                    plan.currency,
                                                                    installment.amount,
                                                                )}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {cycle.type ===
                                                                'fixed'
                                                                    ? `Due ${installment.due_on ?? 'date not set'}`
                                                                    : `${installment.due_offset_days ?? 0} days after activation`}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
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
