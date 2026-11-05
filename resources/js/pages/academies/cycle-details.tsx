import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Banknote, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    currencySymbol,
    formatCurrency,
    formatMoneyInput,
    normalizeMoneyInput,
    sumMoney,
} from '@/lib/money';
import type { AcademyCycle } from './cycles';

type Option = { id: number; name: string };
type FeeRow = { name: string; amount: string };
type InstallmentRow = {
    name: string;
    amount: string;
    due_on: string;
    due_offset_days: string;
};
type Offering = {
    id: number;
    programme_id: number;
    fee_name: string;
    branch_id: number | null;
    branch_name: string | null;
    programme_name: string;
    currency: string;
    application_fee: string;
    fee_total: string;
    payment_mode: 'one_off' | 'installments';
    is_active: boolean;
    fee_items: FeeRow[];
    installments: Array<{
        name: string;
        amount: string;
        due_on: string | null;
        due_offset_days: number | null;
    }>;
};
type CycleDetails = AcademyCycle & { offerings: Offering[] };
type CycleFee = {
    id: number;
    name: string;
    category: string;
    amount: string;
    currency: string;
    is_compulsory: boolean;
    applies_to_all_programmes: boolean;
    is_active: boolean;
    programmes: Option[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

function CycleFeeDialog({
    cycle,
    programmes,
    currency,
}: {
    cycle: CycleDetails;
    programmes: Option[];
    currency: string;
}) {
    const [allProgrammes, setAllProgrammes] = useState(true);
    const form = useForm({
        name: '',
        category: '',
        amount: '',
        is_compulsory: false,
        applies_to_all_programmes: true,
        is_active: true,
        programme_ids: [] as number[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/app/finance/cycles/${cycle.id}/fees`, {
            onSuccess: () => form.reset(),
        });
    }

    function toggleProgramme(programmeId: number, checked: boolean) {
        form.setData(
            'programme_ids',
            checked
                ? [...form.data.programme_ids, programmeId]
                : form.data.programme_ids.filter((id) => id !== programmeId),
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    Add cycle fee
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add bookable fee</DialogTitle>
                    <DialogDescription>
                        Create a compulsory or optional fee for all or selected
                        programmes in this cycle.
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={submit}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Fee name</Label>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                                placeholder="Medical fee"
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Category</Label>
                            <Input
                                value={form.data.category}
                                onChange={(event) =>
                                    form.setData('category', event.target.value)
                                }
                                required
                                placeholder="Medical, insurance, kit…"
                            />
                            <InputError message={form.errors.category} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{`Amount (${currencySymbol(currency)})`}</Label>
                            <Input
                                value={formatMoneyInput(form.data.amount)}
                                onChange={(event) =>
                                    form.setData(
                                        'amount',
                                        normalizeMoneyInput(event.target.value),
                                    )
                                }
                                required
                                inputMode="decimal"
                            />
                            <InputError message={form.errors.amount} />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_compulsory}
                            onChange={(event) =>
                                form.setData(
                                    'is_compulsory',
                                    event.target.checked,
                                )
                            }
                        />
                        Compulsory — automatically invoice eligible players
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={allProgrammes}
                            onChange={(event) => {
                                setAllProgrammes(event.target.checked);
                                form.setData(
                                    'applies_to_all_programmes',
                                    event.target.checked,
                                );
                                if (event.target.checked)
                                    form.setData('programme_ids', []);
                            }}
                        />
                        Apply to all programmes
                    </label>
                    {!allProgrammes && (
                        <div className="grid gap-2 rounded-lg border p-3">
                            <Label>Selected programmes</Label>
                            {programmes.map((programme) => (
                                <label
                                    key={programme.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.programme_ids.includes(
                                            programme.id,
                                        )}
                                        onChange={(event) =>
                                            toggleProgramme(
                                                programme.id,
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    {programme.name}
                                </label>
                            ))}
                            <InputError message={form.errors.programme_ids} />
                        </div>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}Save fee
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function OfferingDialog({
    cycle,
    programmes,
    branches,
    currency,
    offering,
}: {
    cycle: CycleDetails;
    programmes: Option[];
    branches: Option[];
    currency: string;
    offering?: Offering;
}) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(offering);
    const form = useForm({
        programme_id: String(offering?.programme_id ?? ''),
        fee_name: offering?.fee_name ?? 'Programme fee',
        branch_id: String(offering?.branch_id ?? ''),
        application_fee: offering?.application_fee ?? '0.00',
        payment_mode: offering?.payment_mode ?? ('one_off' as const),
        is_active: offering?.is_active ?? true,
        fee_items: offering?.fee_items ?? [{ name: 'Tuition', amount: '' }],
        installments: offering?.installments.map((item) => ({
            name: item.name,
            amount: item.amount,
            due_on: item.due_on ?? '',
            due_offset_days: item.due_offset_days?.toString() ?? '',
        })) ?? [
            {
                name: 'First installment',
                amount: '',
                due_on: '',
                due_offset_days: '0',
            },
            {
                name: 'Final installment',
                amount: '',
                due_on: '',
                due_offset_days: '',
            },
        ],
    });
    const programmeTotal = sumMoney(
        form.data.fee_items.map((item) => item.amount),
    );
    const totalPayable = sumMoney([form.data.application_fee, programmeTotal]);

    function updateFee(index: number, key: keyof FeeRow, value: string) {
        form.setData(
            'fee_items',
            form.data.fee_items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [key]: value } : item,
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
            form.data.installments.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [key]: value } : item,
            ),
        );
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (editing) {
            form.patch(
                `/app/finance/cycles/${cycle.id}/programmes/${offering?.id}`,
                options,
            );
        } else {
            form.post(`/app/finance/cycles/${cycle.id}/programmes`, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {editing ? (
                    <Button variant="outline" size="sm">
                        <Pencil className="size-4" />
                        Edit fees
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" />
                        Add programme fees
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit' : 'Add'} programme fees
                    </DialogTitle>
                    <DialogDescription>
                        Leave the branch blank for academy-wide default fees, or
                        select a branch to override that default. Issued
                        invoices retain their original snapshots.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Programme</Label>
                            <select
                                className={selectClass}
                                value={form.data.programme_id}
                                onChange={(event) =>
                                    form.setData(
                                        'programme_id',
                                        event.target.value,
                                    )
                                }
                                disabled={editing}
                                required
                            >
                                <option value="">Select programme</option>
                                {programmes.map((programme) => (
                                    <option
                                        key={programme.id}
                                        value={programme.id}
                                    >
                                        {programme.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.programme_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Fee name</Label>
                            <Input
                                value={form.data.fee_name}
                                onChange={(event) =>
                                    form.setData('fee_name', event.target.value)
                                }
                                placeholder="Programme fee"
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                This name appears on the player invoice.
                            </p>
                            <InputError message={form.errors.fee_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Branch scope</Label>
                            <select
                                className={selectClass}
                                value={form.data.branch_id}
                                onChange={(event) =>
                                    form.setData(
                                        'branch_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">All branches (default)</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.branch_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>{`Application fee (${currencySymbol(currency)})`}</Label>
                            <Input
                                value={formatMoneyInput(
                                    form.data.application_fee,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'application_fee',
                                        normalizeMoneyInput(event.target.value),
                                    )
                                }
                                inputMode="decimal"
                                placeholder="0.00"
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                Use 0.00 when this programme has no application
                                fee.
                            </p>
                            <InputError message={form.errors.application_fee} />
                        </div>
                    </div>

                    <fieldset className="space-y-3">
                        <legend className="font-medium">Fee breakdown</legend>
                        <p className="text-xs text-muted-foreground">
                            {`Enter all amounts in ${currencySymbol(currency)}. Thousands separators are added automatically.`}
                        </p>
                        {form.data.fee_items.map((item, index) => (
                            <div
                                key={index}
                                className="grid gap-2 sm:grid-cols-[1fr_12rem_auto]"
                            >
                                <Input
                                    aria-label={`Fee ${index + 1} name`}
                                    value={item.name}
                                    onChange={(event) =>
                                        updateFee(
                                            index,
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Tuition"
                                    required
                                />
                                <Input
                                    aria-label={`Fee ${index + 1} amount`}
                                    value={formatMoneyInput(item.amount)}
                                    onChange={(event) =>
                                        updateFee(
                                            index,
                                            'amount',
                                            normalizeMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="decimal"
                                    placeholder="0.00"
                                    required
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove fee ${index + 1}`}
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
                                        form.errors[`fee_items.${index}.name`]
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
                                    { name: '', amount: '' },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Add fee item
                        </Button>
                    </fieldset>

                    <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 sm:grid-cols-3">
                        <p className="text-sm">
                            <span className="block text-muted-foreground">
                                Application fee
                            </span>
                            <span className="font-semibold">
                                {formatCurrency(
                                    currency,
                                    form.data.application_fee,
                                )}
                            </span>
                        </p>
                        <p className="text-sm">
                            <span className="block text-muted-foreground">
                                Programme fees
                            </span>
                            <span className="font-semibold">
                                {formatCurrency(currency, programmeTotal)}
                            </span>
                        </p>
                        <p className="text-sm">
                            <span className="block text-muted-foreground">
                                Total payable
                            </span>
                            <span className="font-semibold text-primary">
                                {formatCurrency(currency, totalPayable)}
                            </span>
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Payment options</Label>
                            <label className="flex items-start gap-2 rounded-md border p-3 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={
                                        form.data.payment_mode ===
                                        'installments'
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'payment_mode',
                                            event.target.checked
                                                ? 'installments'
                                                : 'one_off',
                                        )
                                    }
                                />
                                <span>
                                    <span className="block font-medium">
                                        Add a default installment plan
                                    </span>
                                    <span className="text-muted-foreground">
                                        Full payment is always available. This
                                        schedule is for students who choose to
                                        pay in installments.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    {form.data.payment_mode === 'installments' && (
                        <fieldset className="space-y-3">
                            <legend className="font-medium">
                                Default installment schedule
                            </legend>
                            <p className="text-xs text-muted-foreground">
                                The installment total must equal the fee
                                breakdown total. A student-specific schedule can
                                replace this when their invoice is issued.
                            </p>
                            {form.data.installments.map((item, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <div className="grid gap-1">
                                        <Label>Name</Label>
                                        <Input
                                            value={item.name}
                                            onChange={(event) =>
                                                updateInstallment(
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>{`Amount (${currencySymbol(currency)})`}</Label>
                                        <Input
                                            value={formatMoneyInput(
                                                item.amount,
                                            )}
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
                                    </div>
                                    {cycle.type === 'fixed' ? (
                                        <div className="grid gap-1">
                                            <Label>Due date</Label>
                                            <Input
                                                type="date"
                                                value={item.due_on}
                                                min={
                                                    cycle.starts_on ?? undefined
                                                }
                                                max={cycle.ends_on ?? undefined}
                                                onChange={(event) =>
                                                    updateInstallment(
                                                        index,
                                                        'due_on',
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-1">
                                            <Label>Days after activation</Label>
                                            <Input
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
                                                required
                                            />
                                        </div>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={
                                            form.data.installments.length <= 2
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
                                        Remove
                                    </Button>
                                    <InputError
                                        message={
                                            form.errors[
                                                `installments.${index}.amount`
                                            ]
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors[
                                                `installments.${index}.${cycle.type === 'fixed' ? 'due_on' : 'due_offset_days'}`
                                            ]
                                        }
                                    />
                                </div>
                            ))}
                            <InputError message={form.errors.installments} />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    form.setData('installments', [
                                        ...form.data.installments,
                                        {
                                            name: '',
                                            amount: '',
                                            due_on: '',
                                            due_offset_days: '',
                                        },
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                Add installment
                            </Button>
                        </fieldset>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />
                        Available for new applications and invoices
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}Save programme fees
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function CycleDetailsPage({
    cycle,
    programmes,
    branches,
    cycle_fees,
    currency,
}: {
    cycle: CycleDetails;
    programmes: Option[];
    branches: Option[];
    cycle_fees: CycleFee[];
    currency: string;
}) {
    return (
        <>
            <Head title={cycle.name} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <Button asChild variant="ghost" className="mb-4 -ml-3">
                    <Link href="/app/finance/cycles">
                        <ArrowLeft className="size-4" />
                        Back to cycles
                    </Link>
                </Button>
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <p className="text-sm font-medium text-primary">
                                Academy finance
                            </p>
                            <Badge
                                variant={
                                    cycle.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {cycle.status}
                            </Badge>
                        </div>
                        <h1 className="mt-1 text-2xl font-semibold">
                            {cycle.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {cycle.type === 'fixed'
                                ? `${cycle.starts_on} to ${cycle.ends_on}`
                                : `${cycle.duration_value} ${cycle.duration_unit} from each student’s activation date`}
                        </p>
                    </div>
                    <OfferingDialog
                        cycle={cycle}
                        programmes={programmes}
                        branches={branches}
                        currency={currency}
                    />
                </div>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {cycle.offerings.length === 0 ? (
                            <div className="py-14 text-center">
                                <Banknote className="mx-auto size-9 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No programme fees configured
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Add a programme, its fee breakdown and
                                    payment arrangement.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[940px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Programme
                                            </th>
                                            <th className="px-4 py-3">
                                                Fee name
                                            </th>
                                            <th className="px-4 py-3">
                                                Branch scope
                                            </th>
                                            <th className="px-4 py-3">
                                                Application fee
                                            </th>
                                            <th className="px-4 py-3">
                                                Programme total
                                            </th>
                                            <th className="px-4 py-3">
                                                Payment
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cycle.offerings.map((offering) => (
                                            <tr
                                                key={offering.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {
                                                            offering.programme_name
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {offering.fee_items
                                                            .map(
                                                                (item) =>
                                                                    `${item.name}: ${formatCurrency(offering.currency, item.amount)}`,
                                                            )
                                                            .join(' · ')}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 font-medium">
                                                    {offering.fee_name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {offering.branch_name ??
                                                        'All branches (default)'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatCurrency(
                                                        offering.currency,
                                                        offering.application_fee,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-medium">
                                                    {formatCurrency(
                                                        offering.currency,
                                                        offering.fee_total,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {offering.payment_mode ===
                                                    'one_off'
                                                        ? 'Full payment'
                                                        : `Full payment or ${offering.installments.length} installments`}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            offering.is_active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {offering.is_active
                                                            ? 'Available'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <OfferingDialog
                                                        cycle={cycle}
                                                        programmes={programmes}
                                                        branches={branches}
                                                        currency={currency}
                                                        offering={offering}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <div className="mt-7 mb-4 flex items-end justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Other bookable fees
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Medical, insurance and other compulsory or optional
                            fees.
                        </p>
                    </div>
                    <CycleFeeDialog
                        cycle={cycle}
                        programmes={programmes}
                        currency={currency}
                    />
                </div>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {cycle_fees.length === 0 ? (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No additional cycle fees configured.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Fee</th>
                                            <th className="px-4 py-3">
                                                Amount
                                            </th>
                                            <th className="px-4 py-3">
                                                Programmes
                                            </th>
                                            <th className="px-4 py-3">
                                                Requirement
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cycle_fees.map((fee) => (
                                            <tr
                                                key={fee.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {fee.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground capitalize">
                                                        {fee.category}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatCurrency(
                                                        fee.currency,
                                                        fee.amount,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {fee.applies_to_all_programmes
                                                        ? 'All programmes'
                                                        : fee.programmes
                                                              .map(
                                                                  (programme) =>
                                                                      programme.name,
                                                              )
                                                              .join(', ')}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            fee.is_compulsory
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {fee.is_compulsory
                                                            ? 'Compulsory'
                                                            : 'Optional'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {fee.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
