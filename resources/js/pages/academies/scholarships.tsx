import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    GraduationCap,
    HandCoins,
    HandHeart,
    Pencil,
    Plus,
    ReceiptText,
    Search,
    UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComponentType, FormEvent, ReactNode } from 'react';
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
    formatCurrency,
    formatMoneyInput,
    normalizeMoneyInput,
} from '@/lib/money';

type Option = { id: number; name: string };
type Cycle = Option & {
    terms: Array<{ id: number; name: string }>;
};
type Commitment = {
    id: number;
    sponsor_id: number;
    academy_cycle_id: number | null;
    name: string;
    agreement_reference: string | null;
    benefits: string | null;
    cycle_name: string | null;
    currency: string;
    committed: string;
    received: string;
    remaining: string;
    starts_on: string | null;
    ends_on: string | null;
    renewal_on: string | null;
    status: string;
    notes: string | null;
};
type Sponsor = {
    id: number;
    name: string;
    type: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    status: string;
    commitments: Commitment[];
};
type Scheme = {
    id: number;
    name: string;
    funding_type: 'academy' | 'sponsor';
    sponsor_id: number | null;
    sponsor_name: string | null;
    coverage_type: 'full' | 'percentage' | 'fixed';
    percentage: number | null;
    fixed_amount: string | null;
    currency: string;
    academy_cycle_id: number | null;
    cycle_name: string | null;
    academy_cycle_term_id: number | null;
    term_name: string | null;
    programme_id: number | null;
    programme_name: string | null;
    branch_id: number | null;
    branch_name: string | null;
    starts_on: string | null;
    ends_on: string | null;
    description: string | null;
    is_active: boolean;
    awards_count: number;
};
type Allocation = {
    id: number;
    invoice_id: number;
    invoice_number: string;
    fee_name: string | null;
    currency: string;
    amount: string;
    funded: string;
    outstanding: string;
    status: string;
};
type Award = {
    id: number;
    scheme_id: number;
    scheme_name: string;
    funding_type: 'academy' | 'sponsor';
    sponsor_name: string | null;
    player_id: number;
    player_name: string;
    programme_name: string | null;
    commitment_id: number | null;
    commitment_name: string | null;
    status: string;
    starts_on: string | null;
    ends_on: string | null;
    notes: string | null;
    allocations: Allocation[];
};
type InvoiceOption = {
    id: number;
    player_id: number;
    label: string;
    cycle_id: number;
    programme_id: number | null;
    branch_id: number | null;
};

const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

function SponsorDialog({ sponsor }: { sponsor?: Sponsor }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: sponsor?.name ?? '',
        type: sponsor?.type ?? 'organisation',
        contact_name: sponsor?.contact_name ?? '',
        email: sponsor?.email ?? '',
        phone: sponsor?.phone ?? '',
        address: sponsor?.address ?? '',
        notes: sponsor?.notes ?? '',
        status: sponsor?.status ?? 'active',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            onSuccess: () => setOpen(false),
            preserveScroll: true,
        };
        if (sponsor)
            form.patch(`/app/scholarships/sponsors/${sponsor.id}`, options);
        else form.post('/app/scholarships/sponsors', options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {sponsor ? (
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={`Edit ${sponsor.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" />
                        Add sponsor
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {sponsor ? 'Edit sponsor' : 'Add sponsor'}
                    </DialogTitle>
                    <DialogDescription>
                        Keep the funding organisation or individual and their
                        contact details in one place.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Sponsor name" error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field label="Type" error={form.errors.type}>
                        <select
                            className={selectClass}
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            <option value="organisation">Organisation</option>
                            <option value="individual">Individual</option>
                        </select>
                    </Field>
                    <Field
                        label="Contact person"
                        error={form.errors.contact_name}
                    >
                        <Input
                            value={form.data.contact_name}
                            onChange={(e) =>
                                form.setData('contact_name', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <Input
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Status" error={form.errors.status}>
                        <select
                            className={selectClass}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </Field>
                    <Field label="Address" error={form.errors.address} wide>
                        <textarea
                            className="min-h-20 rounded-md border bg-background p-3 text-sm"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Notes" error={form.errors.notes} wide>
                        <textarea
                            className="min-h-20 rounded-md border bg-background p-3 text-sm"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </Field>
                    <FormFooter
                        processing={form.processing}
                        label={sponsor ? 'Save sponsor' : 'Create sponsor'}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CommitmentDialog({
    sponsors,
    cycles,
    commitment,
}: {
    sponsors: Sponsor[];
    cycles: Cycle[];
    commitment?: Commitment;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        sponsor_id: commitment ? String(commitment.sponsor_id) : '',
        academy_cycle_id: commitment?.academy_cycle_id
            ? String(commitment.academy_cycle_id)
            : '',
        name: commitment?.name ?? '',
        agreement_reference: commitment?.agreement_reference ?? '',
        benefits: commitment?.benefits ?? '',
        amount: commitment ? formatMoneyInput(commitment.committed) : '',
        starts_on: commitment?.starts_on ?? '',
        ends_on: commitment?.ends_on ?? '',
        renewal_on: commitment?.renewal_on ?? '',
        status: commitment?.status ?? 'active',
        notes: commitment?.notes ?? '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            amount: normalizeMoneyInput(data.amount),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!commitment) form.reset();
            },
        };
        if (commitment) {
            form.patch(
                `/app/scholarships/commitments/${commitment.id}`,
                options,
            );
        } else {
            form.post('/app/scholarships/commitments', options);
        }
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={commitment ? 'ghost' : 'outline'}
                    size={commitment ? 'icon' : 'default'}
                    aria-label={
                        commitment ? `Edit ${commitment.name}` : undefined
                    }
                >
                    {commitment ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {!commitment && 'Add commitment'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {commitment
                            ? 'Edit sponsor commitment'
                            : 'Add sponsor commitment'}
                    </DialogTitle>
                    <DialogDescription>
                        Record what a sponsor promised. Cash received is
                        captured separately against a beneficiary invoice.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Sponsor" error={form.errors.sponsor_id}>
                        <select
                            className={selectClass}
                            value={form.data.sponsor_id}
                            onChange={(e) =>
                                form.setData('sponsor_id', e.target.value)
                            }
                            required
                        >
                            <option value="">Select sponsor</option>
                            {sponsors
                                .filter(
                                    (s) =>
                                        s.status === 'active' ||
                                        s.id === commitment?.sponsor_id,
                                )
                                .map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field
                        label="Cycle (optional)"
                        error={form.errors.academy_cycle_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.academy_cycle_id}
                            onChange={(e) =>
                                form.setData('academy_cycle_id', e.target.value)
                            }
                        >
                            <option value="">Any cycle</option>
                            {cycles.map((cycle) => (
                                <option key={cycle.id} value={cycle.id}>
                                    {cycle.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Commitment name" error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="2027 bursary fund"
                            required
                        />
                    </Field>
                    <Field label="Amount committed" error={form.errors.amount}>
                        <Input
                            inputMode="decimal"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData(
                                    'amount',
                                    formatMoneyInput(e.target.value),
                                )
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Agreement reference"
                        error={form.errors.agreement_reference}
                    >
                        <Input
                            value={form.data.agreement_reference}
                            onChange={(e) =>
                                form.setData(
                                    'agreement_reference',
                                    e.target.value,
                                )
                            }
                            placeholder="MOU-2027-01"
                        />
                    </Field>
                    <Field
                        label="Sponsor benefits"
                        error={form.errors.benefits}
                    >
                        <Input
                            value={form.data.benefits}
                            onChange={(e) =>
                                form.setData('benefits', e.target.value)
                            }
                            placeholder="Logo placement, event recognition"
                        />
                    </Field>
                    <Field label="Starts" error={form.errors.starts_on}>
                        <Input
                            type="date"
                            value={form.data.starts_on}
                            onChange={(e) =>
                                form.setData('starts_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Ends" error={form.errors.ends_on}>
                        <Input
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) =>
                                form.setData('ends_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Renewal date" error={form.errors.renewal_on}>
                        <Input
                            type="date"
                            value={form.data.renewal_on}
                            onChange={(e) =>
                                form.setData('renewal_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Status" error={form.errors.status}>
                        <select
                            className={selectClass}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </Field>
                    <Field label="Notes" error={form.errors.notes} wide>
                        <textarea
                            className="min-h-20 rounded-md border bg-background p-3 text-sm"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </Field>
                    <FormFooter
                        processing={form.processing}
                        label={
                            commitment ? 'Save commitment' : 'Create commitment'
                        }
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SchemeDialog({
    scheme,
    sponsors,
    cycles,
    programmes,
    branches,
}: {
    scheme?: Scheme;
    sponsors: Sponsor[];
    cycles: Cycle[];
    programmes: Option[];
    branches: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: scheme?.name ?? '',
        funding_type: scheme?.funding_type ?? 'academy',
        sponsor_id: scheme?.sponsor_id?.toString() ?? '',
        coverage_type: scheme?.coverage_type ?? 'full',
        percentage: scheme?.percentage?.toString() ?? '',
        fixed_amount: scheme?.fixed_amount
            ? formatMoneyInput(scheme.fixed_amount)
            : '',
        academy_cycle_id: scheme?.academy_cycle_id?.toString() ?? '',
        academy_cycle_term_id: scheme?.academy_cycle_term_id?.toString() ?? '',
        programme_id: scheme?.programme_id?.toString() ?? '',
        branch_id: scheme?.branch_id?.toString() ?? '',
        starts_on: scheme?.starts_on ?? '',
        ends_on: scheme?.ends_on ?? '',
        description: scheme?.description ?? '',
        is_active: scheme?.is_active ?? true,
    });
    const terms =
        cycles.find((cycle) => cycle.id === Number(form.data.academy_cycle_id))
            ?.terms ?? [];
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            fixed_amount: data.fixed_amount
                ? normalizeMoneyInput(data.fixed_amount)
                : '',
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!scheme) form.reset();
            },
        };
        if (scheme)
            form.patch(`/app/scholarships/schemes/${scheme.id}`, options);
        else form.post('/app/scholarships/schemes', options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {scheme ? (
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={`Edit ${scheme.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" />
                        New scheme
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {scheme
                            ? 'Edit scholarship scheme'
                            : 'Create scholarship scheme'}
                    </DialogTitle>
                    <DialogDescription>
                        Define who funds it, how much it covers, and the cycle,
                        term, programme or branch scope.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Scheme name" error={form.errors.name} wide>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Funding source"
                        error={form.errors.funding_type}
                    >
                        <select
                            className={selectClass}
                            value={form.data.funding_type}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    funding_type: e.target.value as
                                        | 'academy'
                                        | 'sponsor',
                                    sponsor_id: '',
                                })
                            }
                        >
                            <option value="academy">Academy funded</option>
                            <option value="sponsor">External sponsor</option>
                        </select>
                    </Field>
                    <Field label="Sponsor" error={form.errors.sponsor_id}>
                        <select
                            className={selectClass}
                            value={form.data.sponsor_id}
                            onChange={(e) =>
                                form.setData('sponsor_id', e.target.value)
                            }
                            disabled={form.data.funding_type !== 'sponsor'}
                            required={form.data.funding_type === 'sponsor'}
                        >
                            <option value="">Select sponsor</option>
                            {sponsors
                                .filter((s) => s.status === 'active')
                                .map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field label="Coverage" error={form.errors.coverage_type}>
                        <select
                            className={selectClass}
                            value={form.data.coverage_type}
                            onChange={(e) =>
                                form.setData(
                                    'coverage_type',
                                    e.target.value as
                                        | 'full'
                                        | 'percentage'
                                        | 'fixed',
                                )
                            }
                        >
                            <option value="full">
                                Full outstanding amount
                            </option>
                            <option value="percentage">
                                Percentage of invoice
                            </option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                    </Field>
                    {form.data.coverage_type === 'percentage' ? (
                        <Field
                            label="Percentage"
                            error={form.errors.percentage}
                        >
                            <Input
                                type="number"
                                min="0.01"
                                max="100"
                                step="0.01"
                                value={form.data.percentage}
                                onChange={(e) =>
                                    form.setData('percentage', e.target.value)
                                }
                                required
                            />
                        </Field>
                    ) : form.data.coverage_type === 'fixed' ? (
                        <Field
                            label="Fixed amount"
                            error={form.errors.fixed_amount}
                        >
                            <Input
                                inputMode="decimal"
                                value={form.data.fixed_amount}
                                onChange={(e) =>
                                    form.setData(
                                        'fixed_amount',
                                        formatMoneyInput(e.target.value),
                                    )
                                }
                                required
                            />
                        </Field>
                    ) : (
                        <div />
                    )}
                    <Field
                        label="Cycle (optional)"
                        error={form.errors.academy_cycle_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.academy_cycle_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    academy_cycle_id: e.target.value,
                                    academy_cycle_term_id: '',
                                })
                            }
                        >
                            <option value="">All cycles</option>
                            {cycles.map((cycle) => (
                                <option key={cycle.id} value={cycle.id}>
                                    {cycle.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Term (optional)"
                        error={form.errors.academy_cycle_term_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.academy_cycle_term_id}
                            onChange={(e) =>
                                form.setData(
                                    'academy_cycle_term_id',
                                    e.target.value,
                                )
                            }
                            disabled={!form.data.academy_cycle_id}
                        >
                            <option value="">Whole cycle</option>
                            {terms.map((term) => (
                                <option key={term.id} value={term.id}>
                                    {term.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Programme (optional)"
                        error={form.errors.programme_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.programme_id}
                            onChange={(e) =>
                                form.setData('programme_id', e.target.value)
                            }
                        >
                            <option value="">All programmes</option>
                            {programmes.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Branch (optional)"
                        error={form.errors.branch_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData('branch_id', e.target.value)
                            }
                        >
                            <option value="">All branches</option>
                            {branches.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Valid from" error={form.errors.starts_on}>
                        <Input
                            type="date"
                            value={form.data.starts_on}
                            onChange={(e) =>
                                form.setData('starts_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Valid until" error={form.errors.ends_on}>
                        <Input
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) =>
                                form.setData('ends_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Description"
                        error={form.errors.description}
                        wide
                    >
                        <textarea
                            className="min-h-24 rounded-md border bg-background p-3 text-sm"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm sm:col-span-2">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData('is_active', e.target.checked)
                            }
                        />
                        Scheme is active
                    </label>
                    <FormFooter
                        processing={form.processing}
                        label={scheme ? 'Save scheme' : 'Create scheme'}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AwardDialog({
    schemes,
    sponsors,
    players,
    enrollments,
}: {
    schemes: Scheme[];
    sponsors: Sponsor[];
    players: Option[];
    enrollments: Array<{ id: number; player_id: number; label: string }>;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const form = useForm({
        scholarship_scheme_id: '',
        player_id: '',
        enrollment_id: '',
        sponsor_commitment_id: '',
        starts_on: '',
        ends_on: '',
        notes: '',
    });
    const scheme = schemes.find(
        (item) => item.id === Number(form.data.scholarship_scheme_id),
    );
    const sponsor = sponsors.find((item) => item.id === scheme?.sponsor_id);
    const filteredPlayers = players
        .filter((player) =>
            player.name.toLowerCase().includes(search.toLowerCase()),
        )
        .slice(0, 50);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/app/scholarships/awards', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                setSearch('');
            },
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <GraduationCap className="size-4" />
                    Award scholarship
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Award scholarship</DialogTitle>
                    <DialogDescription>
                        Approve a beneficiary first, then apply the award to one
                        or more eligible invoices.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Scheme"
                        error={form.errors.scholarship_scheme_id}
                        wide
                    >
                        <select
                            className={selectClass}
                            value={form.data.scholarship_scheme_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    scholarship_scheme_id: e.target.value,
                                    sponsor_commitment_id: '',
                                })
                            }
                            required
                        >
                            <option value="">Select scheme</option>
                            {schemes
                                .filter((item) => item.is_active)
                                .map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field label="Search beneficiary" error={undefined} wide>
                        <div className="relative">
                            <Search className="absolute top-3 left-3 size-4 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name"
                            />
                        </div>
                    </Field>
                    <Field
                        label="Beneficiary"
                        error={form.errors.player_id}
                        wide
                    >
                        <select
                            className={selectClass}
                            value={form.data.player_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    player_id: e.target.value,
                                    enrollment_id: '',
                                })
                            }
                            required
                        >
                            <option value="">Select beneficiary</option>
                            {filteredPlayers.map((player) => (
                                <option key={player.id} value={player.id}>
                                    {player.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Enrollment (optional)"
                        error={form.errors.enrollment_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.enrollment_id}
                            onChange={(e) =>
                                form.setData('enrollment_id', e.target.value)
                            }
                            disabled={!form.data.player_id}
                        >
                            <option value="">All eligible invoices</option>
                            {enrollments
                                .filter(
                                    (item) =>
                                        item.player_id ===
                                        Number(form.data.player_id),
                                )
                                .map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.label}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field
                        label="Sponsor commitment"
                        error={form.errors.sponsor_commitment_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.sponsor_commitment_id}
                            onChange={(e) =>
                                form.setData(
                                    'sponsor_commitment_id',
                                    e.target.value,
                                )
                            }
                            disabled={scheme?.funding_type !== 'sponsor'}
                            required={scheme?.funding_type === 'sponsor'}
                        >
                            <option value="">Select commitment</option>
                            {sponsor?.commitments
                                .filter((item) => item.status === 'active')
                                .map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} ·{' '}
                                        {formatCurrency(
                                            item.currency,
                                            item.remaining,
                                        )}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field label="Starts" error={form.errors.starts_on}>
                        <Input
                            type="date"
                            value={form.data.starts_on}
                            onChange={(e) =>
                                form.setData('starts_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Ends" error={form.errors.ends_on}>
                        <Input
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) =>
                                form.setData('ends_on', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Approval notes"
                        error={form.errors.notes}
                        wide
                    >
                        <textarea
                            className="min-h-20 rounded-md border bg-background p-3 text-sm"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </Field>
                    <FormFooter
                        processing={form.processing}
                        label="Approve award"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ApplyAwardDialog({
    award,
    invoices,
}: {
    award: Award;
    invoices: InvoiceOption[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ invoice_id: '' });
    const eligible = invoices.filter(
        (invoice) => invoice.player_id === award.player_id,
    );
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/app/scholarships/awards/${award.id}/allocations`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline" disabled={!eligible.length}>
                    <ReceiptText className="size-4" />
                    Apply to invoice
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Apply {award.scheme_name}</DialogTitle>
                    <DialogDescription>
                        The amount is calculated on the server from the scheme
                        and the beneficiary’s remaining personal balance.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field
                        label="Eligible invoice"
                        error={form.errors.invoice_id}
                    >
                        <select
                            className={selectClass}
                            value={form.data.invoice_id}
                            onChange={(e) =>
                                form.setData('invoice_id', e.target.value)
                            }
                            required
                        >
                            <option value="">Select invoice</option>
                            {eligible.map((invoice) => (
                                <option key={invoice.id} value={invoice.id}>
                                    {invoice.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <FormFooter
                        processing={form.processing}
                        label="Apply scholarship"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ContributionDialog({
    allocation,
    methods,
    today,
}: {
    allocation: Allocation;
    methods: Array<{ value: string; label: string }>;
    today: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        amount: '',
        method: 'bank_transfer',
        received_on: today,
        external_reference: '',
        notes: '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            amount: normalizeMoneyInput(data.amount),
        }));
        form.post(
            `/app/scholarships/allocations/${allocation.id}/contributions`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    form.reset();
                },
            },
        );
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <HandCoins className="size-4" />
                    Record sponsor payment
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record sponsor contribution</DialogTitle>
                    <DialogDescription>
                        {allocation.invoice_number} has{' '}
                        {formatCurrency(
                            allocation.currency,
                            allocation.outstanding,
                        )}{' '}
                        still expected from the sponsor.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Amount" error={form.errors.amount}>
                        <Input
                            inputMode="decimal"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData(
                                    'amount',
                                    formatMoneyInput(e.target.value),
                                )
                            }
                            required
                        />
                    </Field>
                    <Field label="Method" error={form.errors.method}>
                        <select
                            className={selectClass}
                            value={form.data.method}
                            onChange={(e) =>
                                form.setData('method', e.target.value)
                            }
                        >
                            {methods.map((method) => (
                                <option key={method.value} value={method.value}>
                                    {method.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Received on" error={form.errors.received_on}>
                        <Input
                            type="date"
                            max={today}
                            value={form.data.received_on}
                            onChange={(e) =>
                                form.setData('received_on', e.target.value)
                            }
                            required
                        />
                    </Field>
                    <Field
                        label="Reference"
                        error={form.errors.external_reference}
                    >
                        <Input
                            value={form.data.external_reference}
                            onChange={(e) =>
                                form.setData(
                                    'external_reference',
                                    e.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Notes" error={form.errors.notes} wide>
                        <textarea
                            className="min-h-20 rounded-md border bg-background p-3 text-sm"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </Field>
                    <FormFooter
                        processing={form.processing}
                        label="Record contribution"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    error,
    wide = false,
    children,
}: {
    label: string;
    error?: string;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <div className={`grid gap-2 ${wide ? 'sm:col-span-2' : ''}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function FormFooter({
    processing,
    label,
}: {
    processing: boolean;
    label: string;
}) {
    return (
        <DialogFooter className="sm:col-span-2">
            <DialogClose asChild>
                <Button type="button" variant="outline">
                    Cancel
                </Button>
            </DialogClose>
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {label}
            </Button>
        </DialogFooter>
    );
}

export default function Scholarships({
    summary,
    sponsors,
    schemes,
    awards,
    players,
    enrollments,
    invoices,
    cycles,
    programmes,
    branches,
    payment_methods,
    today,
}: {
    summary: {
        currency: string;
        beneficiaries: number;
        awarded: string;
        academy_concessions: string;
        sponsor_outstanding: string;
    };
    sponsors: Sponsor[];
    schemes: Scheme[];
    awards: Award[];
    players: Option[];
    enrollments: Array<{ id: number; player_id: number; label: string }>;
    invoices: InvoiceOption[];
    cycles: Cycle[];
    programmes: Option[];
    branches: Option[];
    payment_methods: Array<{ value: string; label: string }>;
    today: string;
}) {
    const [tab, setTab] = useState<
        'overview' | 'sponsors' | 'schemes' | 'beneficiaries'
    >('overview');
    const tabs = [
        ['overview', 'Overview'],
        ['sponsors', 'Sponsors'],
        ['schemes', 'Schemes'],
        ['beneficiaries', 'Beneficiaries'],
    ] as const;
    const cards = useMemo(
        () =>
            [
                [
                    'Beneficiaries',
                    summary.beneficiaries.toString(),
                    UsersRound,
                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                ],
                [
                    'Scholarships awarded',
                    formatCurrency(summary.currency, summary.awarded),
                    GraduationCap,
                    'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
                ],
                [
                    'Academy concessions',
                    formatCurrency(
                        summary.currency,
                        summary.academy_concessions,
                    ),
                    HandHeart,
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                ],
                [
                    'Sponsor outstanding',
                    formatCurrency(
                        summary.currency,
                        summary.sponsor_outstanding,
                    ),
                    HandCoins,
                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                ],
            ] as const,
        [summary],
    );
    return (
        <>
            <Head title="Scholarships and sponsorships" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy finance
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Scholarships and sponsorships
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Separate academy concessions, beneficiary
                            responsibility and sponsor receivables without
                            altering original invoice values.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <SponsorDialog />
                        <SchemeDialog
                            sponsors={sponsors}
                            cycles={cycles}
                            programmes={programmes}
                            branches={branches}
                        />
                        <AwardDialog
                            schemes={schemes}
                            sponsors={sponsors}
                            players={players}
                            enrollments={enrollments}
                        />
                    </div>
                </div>
                <div
                    role="tablist"
                    className="mb-6 flex gap-1 overflow-x-auto border-b"
                >
                    {tabs.map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={tab === value}
                            onClick={() => setTab(value)}
                            className={`border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap ${tab === value ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'}`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                {tab === 'overview' && (
                    <section className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {cards.map(([label, value, Icon, color]) => (
                                <Card key={label} className={color}>
                                    <CardContent>
                                        <Icon className="mb-4 size-5" />
                                        <p className="text-sm opacity-80">
                                            {label}
                                        </p>
                                        <p className="mt-1 text-2xl font-semibold">
                                            {value}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        <Card>
                            <CardContent>
                                <h2 className="font-semibold">
                                    How responsibility is tracked
                                </h2>
                                <div className="mt-4 grid gap-4 text-sm md:grid-cols-3">
                                    <div className="rounded-lg border p-4">
                                        <p className="font-medium">
                                            Original invoice
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            The billed fee remains unchanged for
                                            audit and reporting.
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-4">
                                        <p className="font-medium">
                                            Beneficiary portion
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            Academy concessions and assigned
                                            sponsor amounts reduce what the
                                            beneficiary personally owes.
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-4">
                                        <p className="font-medium">
                                            Sponsor receivable
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            Externally funded amounts remain due
                                            to the academy until a verified
                                            contribution is recorded.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </section>
                )}
                {tab === 'sponsors' && (
                    <section className="space-y-4">
                        <div className="flex justify-end">
                            <CommitmentDialog
                                sponsors={sponsors}
                                cycles={cycles}
                            />
                        </div>
                        {sponsors.length === 0 ? (
                            <Empty
                                icon={Building2}
                                title="No sponsors yet"
                                text="Add an organisation or individual, then record what they have committed."
                            />
                        ) : (
                            sponsors.map((sponsor) => (
                                <Card key={sponsor.id}>
                                    <CardContent>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h2 className="font-semibold">
                                                        {sponsor.name}
                                                    </h2>
                                                    <Badge
                                                        variant={
                                                            sponsor.status ===
                                                            'active'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {sponsor.status}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {sponsor.contact_name ??
                                                        'No contact person'}{' '}
                                                    ·{' '}
                                                    {sponsor.email ??
                                                        sponsor.phone ??
                                                        'No contact details'}
                                                </p>
                                            </div>
                                            <SponsorDialog sponsor={sponsor} />
                                        </div>
                                        <div className="mt-5 overflow-x-auto">
                                            <table className="w-full min-w-[720px] text-left text-sm">
                                                <thead className="border-b text-xs text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="py-2">
                                                            Commitment
                                                        </th>
                                                        <th>Cycle</th>
                                                        <th>Committed</th>
                                                        <th>Received</th>
                                                        <th>Remaining</th>
                                                        <th>Renewal</th>
                                                        <th className="text-right">
                                                            Action
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {sponsor.commitments.map(
                                                        (item) => (
                                                            <tr
                                                                key={item.id}
                                                                className="border-b last:border-0"
                                                            >
                                                                <td className="py-3 font-medium">
                                                                    <p>
                                                                        {
                                                                            item.name
                                                                        }
                                                                    </p>
                                                                    {(item.agreement_reference ||
                                                                        item.benefits) && (
                                                                        <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                                            {[
                                                                                item.agreement_reference,
                                                                                item.benefits,
                                                                            ]
                                                                                .filter(
                                                                                    Boolean,
                                                                                )
                                                                                .join(
                                                                                    ' · ',
                                                                                )}
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {item.cycle_name ??
                                                                        'Any cycle'}
                                                                </td>
                                                                <td>
                                                                    {formatCurrency(
                                                                        item.currency,
                                                                        item.committed,
                                                                    )}
                                                                </td>
                                                                <td className="text-emerald-700">
                                                                    {formatCurrency(
                                                                        item.currency,
                                                                        item.received,
                                                                    )}
                                                                </td>
                                                                <td className="text-amber-700">
                                                                    {formatCurrency(
                                                                        item.currency,
                                                                        item.remaining,
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    {item.renewal_on ??
                                                                        '—'}
                                                                </td>
                                                                <td className="text-right">
                                                                    <CommitmentDialog
                                                                        sponsors={
                                                                            sponsors
                                                                        }
                                                                        cycles={
                                                                            cycles
                                                                        }
                                                                        commitment={
                                                                            item
                                                                        }
                                                                    />
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                            {sponsor.commitments.length ===
                                                0 && (
                                                <p className="py-5 text-sm text-muted-foreground">
                                                    No commitments recorded.
                                                </p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </section>
                )}
                {tab === 'schemes' && (
                    <section>
                        {schemes.length === 0 ? (
                            <Empty
                                icon={GraduationCap}
                                title="No scholarship schemes"
                                text="Create a full, percentage or fixed-value scholarship with optional cycle, term, programme and branch scope."
                            />
                        ) : (
                            <div className="overflow-hidden rounded-xl border bg-card">
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[900px] text-left text-sm">
                                        <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Scheme
                                                </th>
                                                <th>Funding</th>
                                                <th>Coverage</th>
                                                <th>Scope</th>
                                                <th>Validity</th>
                                                <th>Beneficiaries</th>
                                                <th className="w-14" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {schemes.map((scheme) => (
                                                <tr
                                                    key={scheme.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        <p className="font-medium">
                                                            {scheme.name}
                                                        </p>
                                                        <Badge
                                                            variant={
                                                                scheme.is_active
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                            className="mt-1"
                                                        >
                                                            {scheme.is_active
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </Badge>
                                                    </td>
                                                    <td>
                                                        {scheme.funding_type ===
                                                        'academy'
                                                            ? 'Academy funded'
                                                            : scheme.sponsor_name}
                                                    </td>
                                                    <td>
                                                        {scheme.coverage_type ===
                                                        'full'
                                                            ? 'Full balance'
                                                            : scheme.coverage_type ===
                                                                'percentage'
                                                              ? `${scheme.percentage}%`
                                                              : formatCurrency(
                                                                    scheme.currency,
                                                                    scheme.fixed_amount ??
                                                                        '0.00',
                                                                )}
                                                    </td>
                                                    <td>
                                                        {[
                                                            scheme.cycle_name,
                                                            scheme.term_name,
                                                            scheme.programme_name,
                                                            scheme.branch_name,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') ||
                                                            'All eligible fees'}
                                                    </td>
                                                    <td>
                                                        {scheme.starts_on ??
                                                            'Open'}{' '}
                                                        –{' '}
                                                        {scheme.ends_on ??
                                                            'No end'}
                                                    </td>
                                                    <td>
                                                        {scheme.awards_count}
                                                    </td>
                                                    <td>
                                                        <SchemeDialog
                                                            scheme={scheme}
                                                            sponsors={sponsors}
                                                            cycles={cycles}
                                                            programmes={
                                                                programmes
                                                            }
                                                            branches={branches}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </section>
                )}
                {tab === 'beneficiaries' && (
                    <section className="space-y-4">
                        {awards.length === 0 ? (
                            <Empty
                                icon={UsersRound}
                                title="No beneficiaries"
                                text="Award an active scholarship scheme to a beneficiary, then apply it to eligible invoices."
                            />
                        ) : (
                            awards.map((award) => (
                                <Card key={award.id}>
                                    <CardContent>
                                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="font-semibold">
                                                        {award.player_name}
                                                    </h2>
                                                    <Badge>
                                                        {award.status}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {award.scheme_name}
                                                    {award.sponsor_name
                                                        ? ` · ${award.sponsor_name}`
                                                        : ' · Academy funded'}
                                                    {award.programme_name
                                                        ? ` · ${award.programme_name}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <ApplyAwardDialog
                                                award={award}
                                                invoices={invoices}
                                            />
                                        </div>
                                        <div className="mt-5 overflow-x-auto">
                                            <table className="w-full min-w-[760px] text-left text-sm">
                                                <thead className="border-b text-xs text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="py-2">
                                                            Invoice
                                                        </th>
                                                        <th>Fee</th>
                                                        <th>Awarded</th>
                                                        <th>Funded</th>
                                                        <th>Outstanding</th>
                                                        <th className="text-right">
                                                            Action
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {award.allocations.map(
                                                        (allocation) => (
                                                            <tr
                                                                key={
                                                                    allocation.id
                                                                }
                                                                className="border-b last:border-0"
                                                            >
                                                                <td className="py-3 font-medium">
                                                                    {
                                                                        allocation.invoice_number
                                                                    }
                                                                </td>
                                                                <td>
                                                                    {allocation.fee_name ??
                                                                        'Fee'}
                                                                </td>
                                                                <td>
                                                                    {formatCurrency(
                                                                        allocation.currency,
                                                                        allocation.amount,
                                                                    )}
                                                                </td>
                                                                <td className="text-emerald-700">
                                                                    {formatCurrency(
                                                                        allocation.currency,
                                                                        allocation.funded,
                                                                    )}
                                                                </td>
                                                                <td className="text-amber-700">
                                                                    {formatCurrency(
                                                                        allocation.currency,
                                                                        allocation.outstanding,
                                                                    )}
                                                                </td>
                                                                <td className="text-right">
                                                                    {award.funding_type ===
                                                                        'sponsor' &&
                                                                        Number(
                                                                            allocation.outstanding,
                                                                        ) >
                                                                            0 && (
                                                                            <ContributionDialog
                                                                                allocation={
                                                                                    allocation
                                                                                }
                                                                                methods={
                                                                                    payment_methods
                                                                                }
                                                                                today={
                                                                                    today
                                                                                }
                                                                            />
                                                                        )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                            {award.allocations.length === 0 && (
                                                <p className="py-5 text-sm text-muted-foreground">
                                                    This award has not been
                                                    applied to an invoice.
                                                </p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </section>
                )}
            </main>
        </>
    );
}

function Empty({
    icon: Icon,
    title,
    text,
}: {
    icon: ComponentType<{ className?: string }>;
    title: string;
    text: string;
}) {
    return (
        <div className="rounded-xl border bg-card py-16 text-center">
            <Icon className="mx-auto size-9 text-muted-foreground" />
            <h2 className="mt-4 font-semibold">{title}</h2>
            <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                {text}
            </p>
        </div>
    );
}
