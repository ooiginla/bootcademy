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
type Term = {
    id: number;
    name: string;
    starts_on: string | null;
    ends_on: string | null;
    sort_order: number;
    is_default: boolean;
    is_active: boolean;
};
type CohortOption = Option & {
    programme_cycle_id: number;
    programme_id: number;
    programme_name: string;
    branch_id: number | null;
    branch_name: string | null;
};
type FeeRow = {
    id?: number;
    name: string;
    amount: string;
    academy_cycle_term_id: number | null;
    term_name?: string | null;
    cohort_id: number | null;
    cohort_name?: string | null;
};
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
type CycleDetails = AcademyCycle & { offerings: Offering[]; terms: Term[] };
type CycleFee = {
    id: number;
    name: string;
    category: string;
    amount: string;
    currency: string;
    is_compulsory: boolean;
    applies_to_all_programmes: boolean;
    academy_cycle_term_id: number | null;
    term_name: string | null;
    cohort_id: number | null;
    cohort_name: string | null;
    is_active: boolean;
    programmes: Option[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

function feeDetailsUrl(
    cycleId: number,
    offeringId: number,
    item: FeeRow,
): string {
    const query = new URLSearchParams();
    if (item.academy_cycle_term_id)
        query.set('term_id', String(item.academy_cycle_term_id));
    if (item.cohort_id) query.set('cohort_id', String(item.cohort_id));
    const suffix = query.toString();

    return `/app/finance/cycles/${cycleId}/programmes/${offeringId}${suffix ? `?${suffix}` : ''}`;
}

function CycleFeeDialog({
    cycle,
    programmes,
    currency,
    terms,
    cohorts,
}: {
    cycle: CycleDetails;
    programmes: Option[];
    currency: string;
    terms: Term[];
    cohorts: CohortOption[];
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
        academy_cycle_term_id: '',
        cohort_id: '',
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
                <Button className="h-10 bg-orange-600 text-white hover:bg-orange-700">
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
                        {cycle.type === 'fixed' && (
                            <div className="grid gap-2">
                                <Label>Term scope</Label>
                                <select
                                    className={selectClass}
                                    value={form.data.academy_cycle_term_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'academy_cycle_term_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">General cycle fee</option>
                                    {terms.map((term) => (
                                        <option key={term.id} value={term.id}>
                                            {term.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.academy_cycle_term_id}
                                />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label>Cohort scope</Label>
                            <select
                                className={selectClass}
                                value={form.data.cohort_id}
                                onChange={(event) =>
                                    form.setData(
                                        'cohort_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">All eligible cohorts</option>
                                {cohorts
                                    .filter(
                                        (cohort) =>
                                            allProgrammes ||
                                            form.data.programme_ids.includes(
                                                cohort.programme_id,
                                            ),
                                    )
                                    .map((cohort) => (
                                        <option
                                            key={cohort.id}
                                            value={cohort.id}
                                        >
                                            {cohort.programme_name} ·{' '}
                                            {cohort.name}
                                        </option>
                                    ))}
                            </select>
                            <InputError message={form.errors.cohort_id} />
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
    terms,
    cohorts,
    offering,
}: {
    cycle: CycleDetails;
    programmes: Option[];
    branches: Option[];
    currency: string;
    terms: Term[];
    cohorts: CohortOption[];
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
        fee_items: offering?.fee_items ?? [
            {
                name: 'Tuition',
                amount: '',
                academy_cycle_term_id:
                    terms.find((term) => term.is_default)?.id ??
                    terms[0]?.id ??
                    null,
                cohort_id: null,
            },
        ],
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

    function updateFee(
        index: number,
        key: keyof FeeRow,
        value: string | number | null,
    ) {
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
                    <Button className="h-10 bg-primary text-primary-foreground">
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
                            {!editing &&
                                ' Save the programme offering first, then edit it to target an existing cohort.'}
                        </p>
                        {form.data.fee_items.map((item, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2 lg:grid-cols-[1fr_11rem_12rem_12rem_auto]"
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
                                {cycle.type === 'fixed' && (
                                    <select
                                        aria-label={`Fee ${index + 1} term`}
                                        className={selectClass}
                                        value={item.academy_cycle_term_id ?? ''}
                                        onChange={(event) =>
                                            updateFee(
                                                index,
                                                'academy_cycle_term_id',
                                                event.target.value
                                                    ? Number(event.target.value)
                                                    : null,
                                            )
                                        }
                                    >
                                        <option value="">
                                            General cycle / all terms
                                        </option>
                                        {terms.map((term) => (
                                            <option
                                                key={term.id}
                                                value={term.id}
                                            >
                                                {term.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <select
                                    aria-label={`Fee ${index + 1} cohort`}
                                    className={selectClass}
                                    value={item.cohort_id ?? ''}
                                    onChange={(event) =>
                                        updateFee(
                                            index,
                                            'cohort_id',
                                            event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        )
                                    }
                                    disabled={!editing}
                                >
                                    <option value="">All cohorts</option>
                                    {cohorts
                                        .filter(
                                            (cohort) =>
                                                cohort.programme_cycle_id ===
                                                offering?.id,
                                        )
                                        .map((cohort) => (
                                            <option
                                                key={cohort.id}
                                                value={cohort.id}
                                            >
                                                {cohort.name}
                                            </option>
                                        ))}
                                </select>
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
                                        form.errors[
                                            `fee_items.${index}.academy_cycle_term_id`
                                        ]
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors[
                                            `fee_items.${index}.cohort_id`
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
                                    {
                                        name: '',
                                        amount: '',
                                        academy_cycle_term_id:
                                            terms.find(
                                                (term) => term.is_default,
                                            )?.id ??
                                            terms[0]?.id ??
                                            null,
                                        cohort_id: null,
                                    },
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

function TermDialog({ cycle, term }: { cycle: CycleDetails; term?: Term }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: term?.name ?? '',
        starts_on: term?.starts_on ?? '',
        ends_on: term?.ends_on ?? '',
        sort_order: term?.sort_order ?? cycle.terms.length + 1,
        is_default: term?.is_default ?? false,
        is_active: term?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (term)
            form.patch(
                `/app/finance/cycles/${cycle.id}/terms/${term.id}`,
                options,
            );
        else form.post(`/app/finance/cycles/${cycle.id}/terms`, options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {term ? (
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        aria-label={`Edit ${term.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                ) : (
                    <Button
                        className="h-10 border-violet-300 bg-violet-50 text-violet-800 hover:bg-violet-100"
                        variant="outline"
                    >
                        <Plus className="size-4" />
                        Add term
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{term ? 'Edit' : 'Add'} term</DialogTitle>
                    <DialogDescription>
                        Terms divide this cycle into reporting and fee periods.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Term name</Label>
                        <Input
                            required
                            placeholder="Term 1"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Starts</Label>
                            <Input
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
                            <Label>Ends</Label>
                            <Input
                                type="date"
                                value={form.data.ends_on}
                                onChange={(event) =>
                                    form.setData('ends_on', event.target.value)
                                }
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Display order</Label>
                        <Input
                            type="number"
                            min={1}
                            max={1000}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_default}
                            onChange={(event) =>
                                form.setData('is_default', event.target.checked)
                            }
                        />
                        Use as the default term for new programme fees
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />
                        Term is active
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            {form.processing && <Spinner />}Save term
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CycleFeesTable({ fees }: { fees: CycleFee[] }) {
    if (fees.length === 0)
        return (
            <p className="p-8 text-center text-sm text-muted-foreground">
                No additional fees in this scope.
            </p>
        );
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm">
                <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">Fee</th>
                        <th className="px-4 py-3">Amount</th>
                        <th className="px-4 py-3">Programmes</th>
                        <th className="px-4 py-3">Cohort</th>
                        <th className="px-4 py-3">Requirement</th>
                        <th className="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {fees.map((fee) => (
                        <tr key={fee.id} className="border-b last:border-0">
                            <td className="px-4 py-3">
                                <p className="font-medium">{fee.name}</p>
                                <p className="text-xs text-muted-foreground capitalize">
                                    {fee.category}
                                </p>
                            </td>
                            <td className="px-4 py-3">
                                {formatCurrency(fee.currency, fee.amount)}
                            </td>
                            <td className="px-4 py-3">
                                {fee.applies_to_all_programmes
                                    ? 'All programmes'
                                    : fee.programmes
                                          .map((programme) => programme.name)
                                          .join(', ')}
                            </td>
                            <td className="px-4 py-3">
                                {fee.cohort_name ?? 'All cohorts'}
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
                                {fee.is_active ? 'Active' : 'Inactive'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ProgrammeFeesTable({
    offerings,
    cycle,
    programmes,
    branches,
    currency,
    terms,
    cohorts,
}: {
    offerings: Array<Offering & { scoped_items: FeeRow[] }>;
    cycle: CycleDetails;
    programmes: Option[];
    branches: Option[];
    currency: string;
    terms: Term[];
    cohorts: CohortOption[];
}) {
    if (offerings.length === 0) {
        return (
            <p className="p-8 text-center text-sm text-muted-foreground">
                No programme fees in this scope.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[880px] text-left text-sm">
                <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">Programme</th>
                        <th className="px-4 py-3">Fee</th>
                        <th className="px-4 py-3">Branch</th>
                        <th className="px-4 py-3">Cohort</th>
                        <th className="px-4 py-3">Amount</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {offerings.flatMap((offering) =>
                        offering.scoped_items.map((item, index) => (
                            <tr
                                key={item.id ?? `${offering.id}-${index}`}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3">
                                    <p className="font-medium">
                                        {offering.programme_name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Application:{' '}
                                        {formatCurrency(
                                            offering.currency,
                                            offering.application_fee,
                                        )}
                                    </p>
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {item.name}
                                </td>
                                <td className="px-4 py-3">
                                    {offering.branch_name ?? 'All branches'}
                                </td>
                                <td className="px-4 py-3">
                                    {item.cohort_name ?? 'All cohorts'}
                                </td>
                                <td className="px-4 py-3 font-semibold">
                                    {formatCurrency(
                                        offering.currency,
                                        item.amount,
                                    )}
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
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={feeDetailsUrl(
                                                    cycle.id,
                                                    offering.id,
                                                    item,
                                                )}
                                            >
                                                Details
                                            </Link>
                                        </Button>
                                        <OfferingDialog
                                            cycle={cycle}
                                            programmes={programmes}
                                            branches={branches}
                                            currency={currency}
                                            terms={terms}
                                            cohorts={cohorts}
                                            offering={offering}
                                        />
                                    </div>
                                </td>
                            </tr>
                        )),
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function CycleDetailsPage({
    cycle,
    programmes,
    branches,
    cohorts,
    cycle_fees,
    currency,
}: {
    cycle: CycleDetails;
    programmes: Option[];
    branches: Option[];
    cohorts: CohortOption[];
    cycle_fees: CycleFee[];
    currency: string;
}) {
    const defaultTerm =
        cycle.terms.find((term) => term.is_default) ?? cycle.terms[0];
    const [selectedTermId, setSelectedTermId] = useState<number | null>(
        defaultTerm?.id ?? null,
    );
    const selectedTerm =
        cycle.terms.find((term) => term.id === selectedTermId) ?? defaultTerm;
    const termOfferings = cycle.offerings
        .map((offering) => ({
            ...offering,
            scoped_items: offering.fee_items.filter(
                (item) => item.academy_cycle_term_id === selectedTerm?.id,
            ),
        }))
        .filter((offering) => offering.scoped_items.length > 0);
    const termCycleFees = cycle_fees.filter(
        (fee) => fee.academy_cycle_term_id === selectedTerm?.id,
    );
    const generalCycleFees = cycle_fees.filter(
        (fee) => fee.academy_cycle_term_id === null,
    );
    const generalProgrammeOfferings = cycle.offerings
        .map((offering) => ({
            ...offering,
            scoped_items: offering.fee_items.filter(
                (item) => item.academy_cycle_term_id === null,
            ),
        }))
        .filter((offering) => offering.scoped_items.length > 0);

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
                    <div className="flex flex-wrap gap-2">
                        {cycle.type === 'fixed' && <TermDialog cycle={cycle} />}
                        <OfferingDialog
                            cycle={cycle}
                            programmes={programmes}
                            branches={branches}
                            currency={currency}
                            terms={cycle.terms}
                            cohorts={cohorts}
                        />
                        <CycleFeeDialog
                            cycle={cycle}
                            programmes={programmes}
                            currency={currency}
                            terms={cycle.terms}
                            cohorts={cohorts}
                        />
                    </div>
                </div>

                {cycle.type === 'fixed' && (
                    <div className="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
                        <aside className="space-y-2">
                            <p className="px-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Terms
                            </p>
                            {cycle.terms.map((term) => (
                                <div key={term.id} className="relative">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSelectedTermId(term.id)
                                        }
                                        className={`w-full rounded-lg border px-3 py-3 pr-11 text-left text-sm transition ${selectedTerm?.id === term.id ? 'border-primary bg-primary/10 text-primary' : 'bg-card hover:bg-muted/50'}`}
                                    >
                                        <span className="block font-semibold">
                                            {term.name}
                                        </span>
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            {term.starts_on && term.ends_on
                                                ? `${term.starts_on} – ${term.ends_on}`
                                                : 'Dates not set'}
                                            {term.is_default
                                                ? ' · Default'
                                                : ''}
                                        </span>
                                    </button>
                                    <div className="absolute top-2 right-2">
                                        <TermDialog cycle={cycle} term={term} />
                                    </div>
                                </div>
                            ))}
                        </aside>
                        <section className="min-w-0 space-y-5">
                            {selectedTerm ? (
                                <>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h2 className="text-xl font-semibold">
                                                {selectedTerm.name}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Programme and other bookable
                                                fees assigned to this term.
                                            </p>
                                        </div>
                                    </div>
                                    <h3 className="font-semibold">
                                        Programme term fees
                                    </h3>
                                    <Card className="overflow-hidden py-0">
                                        <CardContent className="p-0">
                                            {termOfferings.length === 0 ? (
                                                <div className="py-12 text-center">
                                                    <Banknote className="mx-auto size-8 text-muted-foreground" />
                                                    <p className="mt-3 font-medium">
                                                        No programme fees in
                                                        this term
                                                    </p>
                                                </div>
                                            ) : (
                                                <div className="overflow-x-auto">
                                                    <table className="w-full min-w-[880px] text-left text-sm">
                                                        <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                            <tr>
                                                                <th className="px-4 py-3">
                                                                    Programme
                                                                </th>
                                                                <th className="px-4 py-3">
                                                                    Fee
                                                                </th>
                                                                <th className="px-4 py-3">
                                                                    Branch
                                                                </th>
                                                                <th className="px-4 py-3">
                                                                    Cohort
                                                                </th>
                                                                <th className="px-4 py-3">
                                                                    Amount
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
                                                            {termOfferings.flatMap(
                                                                (offering) =>
                                                                    offering.scoped_items.map(
                                                                        (
                                                                            item,
                                                                            index,
                                                                        ) => (
                                                                            <tr
                                                                                key={
                                                                                    item.id ??
                                                                                    `${offering.id}-${index}`
                                                                                }
                                                                                className="border-b last:border-0"
                                                                            >
                                                                                <td className="px-4 py-3">
                                                                                    <p className="font-medium">
                                                                                        {
                                                                                            offering.programme_name
                                                                                        }
                                                                                    </p>
                                                                                    <p className="text-xs text-muted-foreground">
                                                                                        Application:{' '}
                                                                                        {formatCurrency(
                                                                                            offering.currency,
                                                                                            offering.application_fee,
                                                                                        )}
                                                                                    </p>
                                                                                </td>
                                                                                <td className="px-4 py-3 font-medium">
                                                                                    {
                                                                                        item.name
                                                                                    }
                                                                                </td>
                                                                                <td className="px-4 py-3">
                                                                                    {offering.branch_name ??
                                                                                        'All branches'}
                                                                                </td>
                                                                                <td className="px-4 py-3">
                                                                                    {item.cohort_name ??
                                                                                        'All cohorts'}
                                                                                </td>
                                                                                <td className="px-4 py-3 font-semibold">
                                                                                    {formatCurrency(
                                                                                        offering.currency,
                                                                                        item.amount,
                                                                                    )}
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
                                                                                    <div className="flex justify-end gap-2">
                                                                                        <Button
                                                                                            asChild
                                                                                            variant="outline"
                                                                                            size="sm"
                                                                                        >
                                                                                            <Link
                                                                                                href={feeDetailsUrl(
                                                                                                    cycle.id,
                                                                                                    offering.id,
                                                                                                    item,
                                                                                                )}
                                                                                            >
                                                                                                Details
                                                                                            </Link>
                                                                                        </Button>
                                                                                        <OfferingDialog
                                                                                            cycle={
                                                                                                cycle
                                                                                            }
                                                                                            programmes={
                                                                                                programmes
                                                                                            }
                                                                                            branches={
                                                                                                branches
                                                                                            }
                                                                                            currency={
                                                                                                currency
                                                                                            }
                                                                                            terms={
                                                                                                cycle.terms
                                                                                            }
                                                                                            cohorts={
                                                                                                cohorts
                                                                                            }
                                                                                            offering={
                                                                                                offering
                                                                                            }
                                                                                        />
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        ),
                                                                    ),
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                    <h3 className="font-semibold">
                                        Additional term fees
                                    </h3>
                                    <Card className="overflow-hidden py-0">
                                        <CardContent className="p-0">
                                            <CycleFeesTable
                                                fees={termCycleFees}
                                            />
                                        </CardContent>
                                    </Card>
                                </>
                            ) : (
                                <Card>
                                    <CardContent className="py-12 text-center">
                                        Create a term to organise this cycle’s
                                        fees.
                                    </CardContent>
                                </Card>
                            )}
                        </section>
                    </div>
                )}

                <div className="mt-8 mb-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {cycle.type === 'rolling'
                                ? 'Cycle fees'
                                : 'General cycle fees'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {cycle.type === 'rolling'
                                ? 'Programme and additional fees for this rolling cycle.'
                                : 'Fees outside the term structure, such as annual registration or insurance.'}
                        </p>
                    </div>
                </div>
                <h3 className="mb-3 font-semibold">Programme cycle fees</h3>
                <Card className="mb-6 overflow-hidden py-0">
                    <CardContent className="p-0">
                        <ProgrammeFeesTable
                            offerings={generalProgrammeOfferings}
                            cycle={cycle}
                            programmes={programmes}
                            branches={branches}
                            currency={currency}
                            terms={cycle.terms}
                            cohorts={cohorts}
                        />
                    </CardContent>
                </Card>
                <h3 className="mb-3 font-semibold">Additional cycle fees</h3>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        <CycleFeesTable fees={generalCycleFees} />
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
