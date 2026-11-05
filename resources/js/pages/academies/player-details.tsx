import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    CalendarRange,
    ReceiptText,
    MoreHorizontal,
    Plus,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import {
    currencySymbol,
    formatCurrency,
    formatMoneyInput,
    normalizeMoneyInput,
} from '@/lib/money';
import {
    CustomizeInstallmentsDialog,
    RecordPaymentDialog,
    type Invoice,
} from './applications';
import {
    EnrollmentDialog,
    GuardianDialog,
    PlayerDialog,
    type CustomField,
    type Option,
    type Player,
} from './players';

type PlayerTab = 'biodata' | 'guardians' | 'invoices' | 'payments';
type CycleAccount = {
    id: number;
    name: string;
    type: string;
    status: string;
    billed: string;
    paid: string;
    owed: string;
    enrollments: Array<{
        id: number;
        programme_id: number;
        programme: string;
        branch_id: number | null;
        branch: string | null;
        status: string;
        starts_on: string;
        ends_on: string | null;
    }>;
    invoices: Array<Invoice & { programme: string }>;
    payments: Array<{
        id: number;
        reference: string;
        amount: string;
        reversed: string;
        net: string;
        method: string;
        status: string;
        received_on: string;
    }>;
};
type PlayerAccount = {
    id: number;
    internal_identifier: string | null;
    name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    phone: string | null;
    whatsapp_number: string | null;
    home_address: string | null;
    city: string | null;
    state_region: string | null;
    country: string | null;
    status: string;
    currency: string;
    total_billed: string;
    total_paid: string;
    total_owed: string;
    guardians: Array<{
        id: number;
        first_name: string;
        last_name: string;
        name: string;
        relationship: string;
        email: string | null;
        phone: string;
        whatsapp_number: string | null;
        is_primary: boolean;
        is_emergency_contact: boolean;
        has_signature: boolean;
    }>;
    custom_fields: Array<{
        label: string;
        value: string | number | boolean | null;
    }>;
    custom_field_values: Record<string, string | number | boolean | null>;
    cycles: CycleAccount[];
};

function Detail({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-medium text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium">
                {value || 'Not provided'}
            </dd>
        </div>
    );
}

function TabButton({
    tab,
    active,
    onSelect,
    children,
}: {
    tab: PlayerTab;
    active: PlayerTab;
    onSelect: (tab: PlayerTab) => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active === tab}
            onClick={() => onSelect(tab)}
            className={`rounded-md px-4 py-2 text-sm font-medium transition-colors ${active === tab ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
        >
            {children}
        </button>
    );
}

function CancelInvoiceDialog({
    invoice,
    onSuccess,
}: {
    invoice: Invoice;
    onSuccess?: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <DropdownMenuItem
                    onSelect={(event) => event.preventDefault()}
                    className="text-destructive"
                >
                    Cancel invoice
                </DropdownMenuItem>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel invoice</DialogTitle>
                    <DialogDescription>
                        Cancel {invoice.number}. The invoice remains in audit
                        history and cannot receive payments.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/finance/invoices/${invoice.id}/cancel`}
                    method="patch"
                    onSuccess={() => {
                        setOpen(false);
                        onSuccess?.();
                    }}
                >
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Reason</Label>
                                <textarea
                                    name="reason"
                                    required
                                    rows={4}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                />
                                {errors.reason && (
                                    <p className="text-sm text-destructive">
                                        {errors.reason}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Close
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}Cancel invoice
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function WriteOffDialog({
    invoice,
    onSuccess,
}: {
    invoice: Invoice;
    onSuccess?: () => void;
}) {
    const [amount, setAmount] = useState(invoice.balance);
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <DropdownMenuItem onSelect={(event) => event.preventDefault()}>
                    Write off balance
                </DropdownMenuItem>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Write off invoice balance</DialogTitle>
                    <DialogDescription>
                        Outstanding:{' '}
                        {formatCurrency(invoice.currency, invoice.balance)}. The
                        amount becomes an academy expense.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/finance/invoices/${invoice.id}/write-offs`}
                    method="post"
                    onSuccess={() => {
                        setOpen(false);
                        onSuccess?.();
                    }}
                >
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label>{`Amount (${currencySymbol(invoice.currency)})`}</Label>
                                <input
                                    type="hidden"
                                    name="amount"
                                    value={amount}
                                />
                                <Input
                                    value={formatMoneyInput(amount)}
                                    onChange={(event) =>
                                        setAmount(
                                            normalizeMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="decimal"
                                    required
                                />
                                {errors.amount && (
                                    <p className="text-sm text-destructive">
                                        {errors.amount}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>Reason</Label>
                                <textarea
                                    name="reason"
                                    required
                                    rows={4}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                />
                                {errors.reason && (
                                    <p className="text-sm text-destructive">
                                        {errors.reason}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Close
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Record write-off
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function InvoiceActions({ invoice }: { invoice: Invoice }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const open = !['paid', 'void', 'written_off'].includes(invoice.status);
    if (!open) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <DropdownMenu open={menuOpen} onOpenChange={setMenuOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Actions for ${invoice.number}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {open && (
                    <RecordPaymentDialog
                        invoice={invoice}
                        trigger={
                            <DropdownMenuItem
                                onSelect={(event) => event.preventDefault()}
                            >
                                Record payment
                            </DropdownMenuItem>
                        }
                    />
                )}
                {invoice.purpose === 'programme' && invoice.paid === '0.00' && (
                    <CustomizeInstallmentsDialog
                        invoice={invoice}
                        trigger={
                            <DropdownMenuItem
                                onSelect={(event) => event.preventDefault()}
                            >
                                Customize payment plan
                            </DropdownMenuItem>
                        }
                    />
                )}
                {open && (
                    <WriteOffDialog
                        invoice={invoice}
                        onSuccess={() => setMenuOpen(false)}
                    />
                )}
                {invoice.status === 'issued' && invoice.paid === '0.00' && (
                    <CancelInvoiceDialog
                        invoice={invoice}
                        onSuccess={() => setMenuOpen(false)}
                    />
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function BookFeeDialog({
    playerId,
    fees,
}: {
    playerId: number;
    fees: Array<{
        id: number;
        name: string;
        cycle_name: string;
        amount: string;
        currency: string;
    }>;
}) {
    if (fees.length === 0) {
        return (
            <Button disabled>
                <Plus className="size-4" />
                Book fee
            </Button>
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    Book fee
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Book fee invoice</DialogTitle>
                    <DialogDescription>
                        Select a configured cycle fee that applies to this
                        player.
                    </DialogDescription>
                </DialogHeader>
                <Form action={`/app/players/${playerId}/fees`} method="post">
                    {({ processing, errors }) => (
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Fee</Label>
                                <select
                                    name="fee_id"
                                    required
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">Select fee</option>
                                    {fees.map((fee) => (
                                        <option key={fee.id} value={fee.id}>
                                            {fee.name} · {fee.cycle_name} ·{' '}
                                            {formatCurrency(
                                                fee.currency,
                                                fee.amount,
                                            )}
                                        </option>
                                    ))}
                                </select>
                                {errors.fee_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.fee_id}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Create invoice
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function PlayerDetails({
    player,
    programmes,
    branches,
    custom_fields,
    bookable_fees,
}: {
    player: PlayerAccount;
    programmes: Option[];
    branches: Option[];
    custom_fields: CustomField[];
    bookable_fees: Array<{
        id: number;
        name: string;
        cycle_name: string;
        amount: string;
        currency: string;
    }>;
}) {
    const terminology = useAcademyTerminology();
    const [activeTab, setActiveTab] = useState<PlayerTab>('biodata');
    const featuredCycle =
        player.cycles.find((cycle) => cycle.status === 'active') ??
        player.cycles[0];
    const invoices = player.cycles.flatMap((cycle) =>
        cycle.invoices.map((invoice) => ({
            ...invoice,
            cycleName: cycle.name,
        })),
    );
    const payments = player.cycles.flatMap((cycle) =>
        cycle.payments.map((payment) => ({
            ...payment,
            cycleName: cycle.name,
        })),
    );
    const editablePlayer: Player = {
        id: player.id,
        internal_identifier: player.internal_identifier,
        first_name: player.first_name,
        middle_name: player.middle_name,
        last_name: player.last_name,
        date_of_birth: player.date_of_birth,
        gender: player.gender,
        phone: player.phone,
        whatsapp_number: player.whatsapp_number,
        home_address: player.home_address,
        city: player.city,
        state_region: player.state_region,
        country: player.country,
        status: player.status,
        guardians: player.guardians.map((guardian) => ({
            id: guardian.id,
            name: guardian.name,
            email: guardian.email,
            phone: guardian.phone,
            relationship: guardian.relationship,
        })),
        enrollments: player.cycles.flatMap((cycle) =>
            cycle.enrollments.map((enrollment) => ({
                id: enrollment.id,
                programme_id: enrollment.programme_id,
                programme_name: enrollment.programme,
                branch_id: enrollment.branch_id,
                branch_name: enrollment.branch,
                status: enrollment.status,
                starts_on: enrollment.starts_on,
                ends_on: enrollment.ends_on,
            })),
        ),
        custom_fields: player.custom_field_values,
    };

    return (
        <>
            <Head title={player.name} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <Button asChild variant="ghost" className="mb-4 -ml-3">
                    <Link href="/app/players">
                        <ArrowLeft className="size-4" /> Back to{' '}
                        {terminology.pluralLower}
                    </Link>
                </Button>
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        {terminology.singular} account
                    </p>
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold">
                            {player.name}
                        </h1>
                        <Badge variant="secondary" className="capitalize">
                            {player.status}
                        </Badge>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {player.internal_identifier ?? 'No internal ID'} · Cycle
                        memberships, invoices and verified payment history
                    </p>
                </div>

                {featuredCycle && (
                    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold">
                                {featuredCycle.name}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {featuredCycle.type === 'fixed'
                                    ? 'Fixed period'
                                    : 'Rolling duration'}
                            </p>
                        </div>
                        <Badge
                            variant={
                                featuredCycle.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                            className="capitalize"
                        >
                            {featuredCycle.status}
                        </Badge>
                    </div>
                )}

                <div className="mb-7 grid gap-4 sm:grid-cols-3">
                    {[
                        ['Total billed', player.total_billed, ReceiptText],
                        ['Total paid', player.total_paid, Banknote],
                        ['Total owed', player.total_owed, CalendarRange],
                    ].map(([label, value, Icon]) => (
                        <Card key={String(label)}>
                            <CardContent className="pt-6">
                                <Icon className="size-5 text-primary" />
                                <p className="mt-3 text-sm text-muted-foreground">
                                    {String(label)}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {formatCurrency(
                                        player.currency,
                                        String(value),
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div
                    role="tablist"
                    aria-label={`${terminology.singular} profile sections`}
                    className="mb-5 flex w-fit max-w-full flex-wrap gap-1 rounded-lg border bg-muted/40 p-1"
                >
                    <TabButton
                        tab="biodata"
                        active={activeTab}
                        onSelect={setActiveTab}
                    >
                        Biodata
                    </TabButton>
                    <TabButton
                        tab="guardians"
                        active={activeTab}
                        onSelect={setActiveTab}
                    >
                        Guardians ({player.guardians.length})
                    </TabButton>
                    <TabButton
                        tab="invoices"
                        active={activeTab}
                        onSelect={setActiveTab}
                    >
                        Invoices ({invoices.length})
                    </TabButton>
                    <TabButton
                        tab="payments"
                        active={activeTab}
                        onSelect={setActiveTab}
                    >
                        Payments ({payments.length})
                    </TabButton>
                </div>

                {activeTab === 'biodata' && (
                    <div role="tabpanel" className="grid gap-5 lg:grid-cols-3">
                        <Card className="lg:col-span-2">
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <UserRound className="size-5 text-primary" />
                                    Personal information
                                </CardTitle>
                                <PlayerDialog
                                    player={editablePlayer}
                                    customFields={custom_fields}
                                />
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-5 sm:grid-cols-2">
                                    <Detail
                                        label="First name"
                                        value={player.first_name}
                                    />
                                    <Detail
                                        label="Middle name"
                                        value={player.middle_name}
                                    />
                                    <Detail
                                        label="Last name"
                                        value={player.last_name}
                                    />
                                    <Detail
                                        label="Internal ID"
                                        value={player.internal_identifier}
                                    />
                                    <Detail
                                        label="Date of birth"
                                        value={player.date_of_birth}
                                    />
                                    <Detail
                                        label="Age"
                                        value={
                                            player.age === null
                                                ? null
                                                : `${player.age} years`
                                        }
                                    />
                                    <Detail
                                        label="Gender"
                                        value={player.gender}
                                    />
                                    <Detail
                                        label="Phone"
                                        value={player.phone}
                                    />
                                    <Detail
                                        label="WhatsApp"
                                        value={player.whatsapp_number}
                                    />
                                    <Detail
                                        label="Home address"
                                        value={player.home_address}
                                    />
                                    <Detail label="City" value={player.city} />
                                    <Detail
                                        label="State"
                                        value={player.state_region}
                                    />
                                    <Detail
                                        label="Country"
                                        value={player.country}
                                    />
                                    {player.custom_fields.map((field) => (
                                        <Detail
                                            key={field.label}
                                            label={field.label}
                                            value={
                                                field.value === null
                                                    ? null
                                                    : String(field.value)
                                            }
                                        />
                                    ))}
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Programme memberships</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {player.cycles.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No programme membership recorded.
                                    </p>
                                ) : (
                                    player.cycles.map((cycle) => (
                                        <section key={cycle.id}>
                                            <p className="text-sm font-semibold">
                                                {cycle.name}
                                            </p>
                                            <div className="mt-2 space-y-2">
                                                {cycle.enrollments.map(
                                                    (enrollment) => (
                                                        <div
                                                            key={enrollment.id}
                                                            className="rounded-lg border p-3 text-sm"
                                                        >
                                                            <p className="font-medium">
                                                                {
                                                                    enrollment.programme
                                                                }
                                                            </p>
                                                            <p className="text-muted-foreground">
                                                                {enrollment.branch ??
                                                                    'No branch'}{' '}
                                                                ·{' '}
                                                                {
                                                                    enrollment.status
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    enrollment.starts_on
                                                                }{' '}
                                                                –{' '}
                                                                {enrollment.ends_on ??
                                                                    'No end date'}
                                                            </p>
                                                            <div className="mt-2">
                                                                <EnrollmentDialog
                                                                    player={
                                                                        editablePlayer
                                                                    }
                                                                    enrollment={{
                                                                        id: enrollment.id,
                                                                        programme_id:
                                                                            enrollment.programme_id,
                                                                        programme_name:
                                                                            enrollment.programme,
                                                                        branch_id:
                                                                            enrollment.branch_id,
                                                                        branch_name:
                                                                            enrollment.branch,
                                                                        status: enrollment.status,
                                                                        starts_on:
                                                                            enrollment.starts_on,
                                                                        ends_on:
                                                                            enrollment.ends_on,
                                                                    }}
                                                                    programmes={
                                                                        programmes
                                                                    }
                                                                    branches={
                                                                        branches
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </section>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {activeTab === 'guardians' && (
                    <div role="tabpanel">
                        {player.guardians.length === 0 ? (
                            <Card>
                                <CardContent className="py-14 text-center">
                                    <UsersRound className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No guardians linked
                                    </h2>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {player.guardians.map((guardian) => (
                                    <Card key={guardian.id}>
                                        <CardHeader className="flex-row items-start justify-between gap-3">
                                            <div>
                                                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                                    {guardian.name}
                                                    {guardian.is_primary && (
                                                        <Badge variant="secondary">
                                                            Primary
                                                        </Badge>
                                                    )}
                                                </CardTitle>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {guardian.relationship}
                                                </p>
                                            </div>
                                            <GuardianDialog
                                                guardian={{
                                                    id: guardian.id,
                                                    first_name:
                                                        guardian.first_name,
                                                    last_name:
                                                        guardian.last_name,
                                                    email: guardian.email,
                                                    phone: guardian.phone,
                                                    whatsapp_number:
                                                        guardian.whatsapp_number,
                                                    has_signature:
                                                        guardian.has_signature,
                                                }}
                                            />
                                        </CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <p>
                                                {guardian.phone || 'No phone'}
                                            </p>
                                            <p>
                                                {guardian.email || 'No email'}
                                            </p>
                                            <p>
                                                WhatsApp:{' '}
                                                {guardian.whatsapp_number ||
                                                    'Not provided'}
                                            </p>
                                            <div className="flex flex-wrap gap-2 pt-1">
                                                {guardian.is_emergency_contact && (
                                                    <Badge variant="outline">
                                                        Emergency contact
                                                    </Badge>
                                                )}
                                                {guardian.has_signature && (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/app/guardians/${guardian.id}/signature`}
                                                        >
                                                            View signature
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'invoices' && (
                    <div role="tabpanel" className="space-y-4">
                        <div className="flex justify-end">
                            <BookFeeDialog
                                playerId={player.id}
                                fees={bookable_fees}
                            />
                        </div>
                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {invoices.length === 0 ? (
                                    <div className="py-14 text-center">
                                        <ReceiptText className="mx-auto size-9 text-muted-foreground" />
                                        <h2 className="mt-4 font-semibold">
                                            No invoices yet
                                        </h2>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[1100px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3">
                                                        Invoice
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Programme / cycle
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Fee name
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Billed
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Paid
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Written off
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Owing
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Status
                                                    </th>
                                                    <th className="px-4 py-3 text-right">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {invoices.map((invoice) => (
                                                    <tr
                                                        key={invoice.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="px-4 py-3 font-mono text-xs">
                                                            {invoice.number}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium">
                                                                {
                                                                    invoice.programme
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    invoice.cycleName
                                                                }
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium">
                                                                {invoice.fee_name ??
                                                                    `${invoice.purpose} fee`}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground capitalize">
                                                                {
                                                                    invoice.purpose
                                                                }{' '}
                                                                fee
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {formatCurrency(
                                                                invoice.currency,
                                                                invoice.total,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-emerald-700 dark:text-emerald-300">
                                                            {formatCurrency(
                                                                invoice.currency,
                                                                invoice.paid,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {formatCurrency(
                                                                invoice.currency,
                                                                invoice.written_off ??
                                                                    '0.00',
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 font-medium text-amber-800 dark:text-amber-200">
                                                            {formatCurrency(
                                                                invoice.currency,
                                                                invoice.balance,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    invoice.status ===
                                                                    'void'
                                                                        ? 'destructive'
                                                                        : invoice.status ===
                                                                            'paid'
                                                                          ? 'secondary'
                                                                          : 'outline'
                                                                }
                                                                className="capitalize"
                                                            >
                                                                {invoice.status ===
                                                                'void'
                                                                    ? 'Cancelled'
                                                                    : invoice.status.replace(
                                                                          '_',
                                                                          ' ',
                                                                      )}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex justify-end">
                                                                <InvoiceActions
                                                                    invoice={
                                                                        invoice
                                                                    }
                                                                />
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {activeTab === 'payments' && (
                    <Card role="tabpanel" className="overflow-hidden py-0">
                        <CardContent className="p-0">
                            {payments.length === 0 ? (
                                <div className="py-14 text-center">
                                    <Banknote className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No verified payments yet
                                    </h2>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[760px] text-left text-sm">
                                        <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Date
                                                </th>
                                                <th className="px-4 py-3">
                                                    Reference
                                                </th>
                                                <th className="px-4 py-3">
                                                    Cycle
                                                </th>
                                                <th className="px-4 py-3">
                                                    Method
                                                </th>
                                                <th className="px-4 py-3">
                                                    Amount
                                                </th>
                                                <th className="px-4 py-3">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Receipt
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {payments.map((payment) => (
                                                <tr
                                                    key={payment.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        {payment.received_on}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs">
                                                        {payment.reference}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {payment.cycleName}
                                                    </td>
                                                    <td className="px-4 py-3 capitalize">
                                                        {payment.method.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 font-medium">
                                                        {formatCurrency(
                                                            player.currency,
                                                            payment.net,
                                                        )}
                                                        {Number(
                                                            payment.reversed,
                                                        ) > 0 && (
                                                            <p className="text-xs text-red-600">
                                                                Reversed{' '}
                                                                {formatCurrency(
                                                                    player.currency,
                                                                    payment.reversed,
                                                                )}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <a
                                                                href={`/app/finance/payments/${payment.id}/receipt`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View
                                                            </a>
                                                        </Button>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant="secondary"
                                                            className="capitalize"
                                                        >
                                                            {payment.status}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}
