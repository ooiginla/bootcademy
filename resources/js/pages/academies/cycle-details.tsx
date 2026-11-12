import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Banknote, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import DeleteRecordButton from '@/components/delete-record-button';
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
    description?: string | null;
    amount: string;
    academy_cycle_term_id: number | null;
    term_name?: string | null;
    cohort_id: number | null;
    cohort_name?: string | null;
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
    scopes: PricingScope[];
};
type CycleDetails = AcademyCycle & { offerings: Offering[]; terms: Term[] };
type PricingScope = {
    academy_cycle_term_id: number | null;
    term_name: string | null;
    cohort_id: number | null;
    cohort_name: string | null;
    plans_count: number;
    public_plans_count: number;
    default_plan_name: string | null;
    default_total: string | null;
};
type ScopeRow = { offering: Offering; scope: PricingScope };
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
    scope: Pick<PricingScope, 'academy_cycle_term_id' | 'cohort_id'>,
): string {
    const query = new URLSearchParams();
    if (scope.academy_cycle_term_id)
        query.set('term_id', String(scope.academy_cycle_term_id));
    if (scope.cohort_id) query.set('cohort_id', String(scope.cohort_id));
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
        is_active: offering?.is_active ?? true,
    });
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
                        invoices keep their original amounts.
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

                    <p className="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                        {editing
                            ? 'Prices are set on this fee’s payment plans. Open Details to add or change them.'
                            : 'After saving, you will add the payment plans that set this fee’s prices.'}
                    </p>

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

function CycleFeesTable({
    cycleId,
    fees,
}: {
    cycleId: number;
    fees: CycleFee[];
}) {
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
                        <th className="px-4 py-3 text-right">Action</th>
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
                            <td className="px-4 py-3 text-right">
                                <DeleteRecordButton
                                    action={`/app/finance/cycles/${cycleId}/fees/${fee.id}`}
                                    label="cycle fee"
                                    name={fee.name}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ProgrammeFeesTable({
    rows,
    cycle,
    programmes,
    branches,
    currency,
    emptyText,
}: {
    rows: ScopeRow[];
    cycle: CycleDetails;
    programmes: Option[];
    branches: Option[];
    currency: string;
    emptyText: string;
}) {
    if (rows.length === 0) {
        return (
            <div className="px-6 py-12 text-center">
                <Banknote className="mx-auto size-8 text-muted-foreground" />
                <p className="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
                    {emptyText}
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left text-sm">
                <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">Programme</th>
                        <th className="px-4 py-3">Branch</th>
                        <th className="px-4 py-3">Cohort</th>
                        <th className="px-4 py-3">Default price</th>
                        <th className="px-4 py-3">Plans</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map(({ offering, scope }) => {
                        const wholeCycle =
                            scope.academy_cycle_term_id === null &&
                            scope.cohort_id === null;

                        return (
                            <tr
                                key={`${offering.id}-${scope.academy_cycle_term_id ?? 0}-${scope.cohort_id ?? 0}`}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3">
                                    <p className="font-medium">
                                        {offering.programme_name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {offering.fee_name} · Application{' '}
                                        {formatCurrency(
                                            offering.currency,
                                            offering.application_fee,
                                        )}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    {offering.branch_name ?? 'All branches'}
                                </td>
                                <td className="px-4 py-3">
                                    {scope.cohort_name ?? 'All cohorts'}
                                </td>
                                <td className="px-4 py-3">
                                    {scope.default_total === null ? (
                                        <span className="text-muted-foreground">
                                            No plans yet
                                        </span>
                                    ) : (
                                        <>
                                            <p className="font-semibold">
                                                {formatCurrency(
                                                    offering.currency,
                                                    scope.default_total,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {scope.default_plan_name}
                                            </p>
                                        </>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <p>{scope.plans_count}</p>
                                    {scope.plans_count > 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            {scope.public_plans_count} on
                                            website
                                        </p>
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
                                                    scope,
                                                )}
                                            >
                                                {scope.plans_count === 0
                                                    ? 'Add plans'
                                                    : 'Details'}
                                            </Link>
                                        </Button>
                                        <OfferingDialog
                                            cycle={cycle}
                                            programmes={programmes}
                                            branches={branches}
                                            currency={currency}
                                            offering={offering}
                                        />
                                        {wholeCycle && (
                                            <DeleteRecordButton
                                                action={`/app/finance/cycles/${cycle.id}/programmes/${offering.id}`}
                                                label="programme fee"
                                                name={
                                                    offering.fee_name ||
                                                    offering.programme_name
                                                }
                                                description="Delete its payment plans first."
                                            />
                                        )}
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
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
    const scopeRows = cycle.offerings.flatMap((offering) =>
        offering.scopes.map((scope) => ({ offering, scope })),
    );
    const termRows = scopeRows.filter(
        ({ scope }) => scope.academy_cycle_term_id === selectedTerm?.id,
    );
    const termCycleFees = cycle_fees.filter(
        (fee) => fee.academy_cycle_term_id === selectedTerm?.id,
    );
    const generalCycleFees = cycle_fees.filter(
        (fee) => fee.academy_cycle_term_id === null,
    );
    const generalRows = scopeRows.filter(
        ({ scope }) => scope.academy_cycle_term_id === null,
    );

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
                        />
                        <CycleFeeDialog
                            cycle={cycle}
                            programmes={programmes}
                            currency={currency}
                            terms={cycle.terms}
                            cohorts={cohorts}
                        />
                        <DeleteRecordButton
                            action={`/app/finance/cycles/${cycle.id}`}
                            label="cycle"
                            name={cycle.name}
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
                                            <ProgrammeFeesTable
                                                rows={termRows}
                                                cycle={cycle}
                                                programmes={programmes}
                                                branches={branches}
                                                currency={currency}
                                                emptyText="No programme fee has its own pricing for this term. Open a programme fee’s details and choose this term to add term plans."
                                            />
                                        </CardContent>
                                    </Card>
                                    <h3 className="font-semibold">
                                        Additional term fees
                                    </h3>
                                    <Card className="overflow-hidden py-0">
                                        <CardContent className="p-0">
                                            <CycleFeesTable
                                                cycleId={cycle.id}
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
                            rows={generalRows}
                            cycle={cycle}
                            programmes={programmes}
                            branches={branches}
                            currency={currency}
                            emptyText="No programme fees yet. Add programme fees to start."
                        />
                    </CardContent>
                </Card>
                <h3 className="mb-3 font-semibold">Additional cycle fees</h3>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        <CycleFeesTable
                            cycleId={cycle.id}
                            fees={generalCycleFees}
                        />
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
